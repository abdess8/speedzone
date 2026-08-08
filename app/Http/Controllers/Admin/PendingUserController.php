<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovePendingUserRequest;
use App\Http\Requests\RejectPendingUserRequest;
use App\Http\Requests\UpdatePendingUserPasswordRequest;
use App\Http\Requests\UpdatePendingUserRequest;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Services\SellerApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PendingUserController extends Controller
{
    public function __construct(private readonly SellerApprovalService $approval) {}

    public function index(Request $request): Response
    {
        $this->authorizeReview();

        $users = User::query()
            ->with(['city', 'role'])
            ->whereHas('roles', fn ($q) => $q->where('name', Role::SELLER))
            ->whereIn('status', [
                UserStatus::PendingApproval->value,
                UserStatus::PendingEmailVerification->value,
                UserStatus::Rejected->value,
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/PendingUsers/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status']),
            'statuses' => UserStatus::options(),
        ]);
    }

    public function show(User $user): Response
    {
        $this->authorizeReview($user);

        $user->load(['city', 'role', 'approvedBy:id,first_name,last_name,name']);

        return Inertia::render('Admin/PendingUsers/Show', [
            'user' => $user,
            'roles' => Role::query()->system()->orderBy('name')->get(['id', 'name']),
            'cities' => City::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => UserStatus::options(),
            'can' => [
                'approve' => $this->approval->canApprove($user),
                'reject' => $this->approval->canReject($user),
                'reactivate' => $this->approval->canReactivate($user),
                // Mirrors the two extra grants update() and updatePassword()
                // demand, so the form does not offer a control that 403s.
                'assign_roles' => auth()->user()->can('assignRoles', User::class),
                'change_password' => auth()->user()->can('update', $user),
            ],
        ]);
    }

    /**
     * Fix the details of an account still under review.
     */
    public function update(UpdatePendingUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeReview($user);

        $data = $request->validated();
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);

        // Reviewing a registration is not the same right as handing out roles.
        // Without this an account holding only `roles.read` could stamp Admin
        // on a pending record and then approve it — the escalation path that
        // `users.roles.assign` exists to close everywhere else.
        $roleChanged = (int) $data['role_id'] !== (int) $user->role_id;

        if ($roleChanged) {
            $this->authorize('assignRoles', User::class);
        }

        // A new address is an unproven one, whatever the old one had been
        // through. The screen offers a resend button for exactly this case.
        $emailChanged = $data['email'] !== $user->email;

        if ($emailChanged) {
            $data['email_verified_at'] = null;
        }

        $user->forceFill($data)->save();

        if ($roleChanged) {
            $user->roles()->sync([$data['role_id']]);
            $user->forgetAccessMemo();
        }

        return redirect()->route('admin.pending-users.show', $user)
            ->with('success', __($emailChanged
                ? 'seller_registration.admin.updated_email_changed'
                : 'seller_registration.admin.updated_success'));
    }

    /**
     * Set a new password on behalf of the account holder.
     */
    public function updatePassword(UpdatePendingUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->authorizeReview($user);
        // Setting someone's password and then approving them is an account
        // takeover, so this asks for the same grant as editing a user anywhere
        // else rather than settling for "may review registrations".
        $this->authorize('update', $user);

        $user->forceFill(['password' => Hash::make($request->validated('password'))])->save();

        return back()->with('success', __('seller_registration.admin.password_updated'));
    }

    public function resendVerification(User $user): RedirectResponse
    {
        $this->authorizeReview($user);
        abort_if($user->hasVerifiedEmail(), 404);

        $user->sendEmailVerificationNotification();

        return back()->with('success', __('seller_registration.admin.verification_sent'));
    }

    public function approve(ApprovePendingUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeReview($user);
        abort_unless($this->approval->canApprove($user), 404);

        $this->approval->approve($user, $request->user(), $this->permissionIds($request));

        return redirect()->route('admin.pending-users.index')
            ->with('success', __('seller_registration.admin.approved_success'));
    }

    public function reject(RejectPendingUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeReview($user);
        abort_unless($this->approval->canReject($user), 404);

        $this->approval->reject($user, $request->user(), $request->validated('rejection_reason'));

        return redirect()->route('admin.pending-users.index')
            ->with('success', __('seller_registration.admin.rejected_success'));
    }

    /**
     * Let a rejected account back in — same activation path as a first-time
     * approval, so the store, the permissions and the e-mail all follow.
     */
    public function reactivate(ApprovePendingUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeReview($user);
        abort_unless($this->approval->canReactivate($user), 404);

        $this->approval->approve($user, $request->user(), $this->permissionIds($request));

        return redirect()->route('admin.pending-users.index')
            ->with('success', __('seller_registration.admin.reactivated_success'));
    }

    /**
     * @return array<int, int>
     */
    private function permissionIds(ApprovePendingUserRequest $request): array
    {
        return $request->validated('permission_ids') ?: $this->approval->defaultPermissionIds();
    }

    /**
     * Guard every entry point: the actor must be a reviewer, and — when a user
     * is being acted upon — that account must still be under review.
     */
    private function authorizeReview(?User $user = null): void
    {
        abort_unless($this->approval->adminCanReview(auth()->user()), 403);

        if ($user && ! $this->approval->isReviewable($user)) {
            abort(404);
        }
    }
}
