<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovePendingUserRequest;
use App\Http\Requests\RejectPendingUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\SellerApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $this->authorizeReview();
        abort_unless($this->approval->isPendingSeller($user) || $user->status === UserStatus::Rejected, 404);

        $user->load(['city', 'permissions:id,name', 'approvedBy:id,first_name,last_name,name']);

        return Inertia::render('Admin/PendingUsers/Show', [
            'user' => $user,
            'permissionGroups' => $this->approval->groupedSellerPermissions(),
            'defaultPermissionIds' => $this->approval->defaultPermissionIds(),
            'statuses' => UserStatus::options(),
        ]);
    }

    public function approve(ApprovePendingUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeReview();
        abort_unless($user->status === UserStatus::PendingApproval, 404);

        $this->approval->approve($user, $request->user(), $request->validated('permission_ids'));

        return redirect()->route('admin.pending-users.index')
            ->with('success', __('seller_registration.admin.approved_success'));
    }

    public function reject(RejectPendingUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeReview();
        abort_unless(in_array($user->status, [UserStatus::PendingApproval, UserStatus::PendingEmailVerification], true), 404);

        $this->approval->reject($user, $request->user(), $request->validated('rejection_reason'));

        return redirect()->route('admin.pending-users.index')
            ->with('success', __('seller_registration.admin.rejected_success'));
    }

    private function authorizeReview(): void
    {
        abort_unless($this->approval->adminCanReview(auth()->user()), 403);
    }
}
