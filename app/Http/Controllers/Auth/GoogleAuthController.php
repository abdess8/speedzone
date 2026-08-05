<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Events\NewSellerRegistered;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Send the visitor to Google's consent screen.
     */
    public function redirect(): Response|RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->failure('seller_registration.google.disabled');
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Sign the visitor in from Google's answer, creating the seller account on
     * first contact.
     */
    public function callback(Request $request): Response|RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->failure('seller_registration.google.disabled');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google sign-in failed.', ['error' => $e->getMessage()]);

            return $this->failure('seller_registration.google.failed');
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return $this->failure('seller_registration.google.no_email');
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first()
            ?? User::query()->where('email', $email)->first();

        if (! $user) {
            $user = $this->createSeller($googleUser, $email);
        } else {
            $this->linkGoogleAccount($user, $googleUser);
        }

        if ($user->isRegistrationRejected()) {
            return $this->failure('seller_registration.login.rejected');
        }

        if ($user->isSuspended()) {
            return $this->failure('team.login.suspended');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Reuses the password login routing, so an account awaiting approval
        // lands on the same holding page whichever way its owner signed in.
        return app(LoginResponseContract::class)->toResponse($request);
    }

    /**
     * Register a seller whose first contact with the platform is Google.
     *
     * The address is already proven by Google, so the account skips straight to
     * the admin approval queue instead of the email verification step.
     */
    private function createSeller(SocialiteUser $googleUser, string $email): User
    {
        $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

        [$firstName, $lastName] = $this->splitName($googleUser->getName() ?? $googleUser->getNickname() ?? $email);

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim($firstName.' '.$lastName),
            'email' => $email,
            'google_id' => $googleUser->getId(),
            // Never used to sign in — the account has no password to type — but
            // the column is not nullable and must not hold a guessable value.
            'password' => Hash::make(Str::random(64)),
            'role_id' => $sellerRole->id,
            'status' => UserStatus::PendingApproval,
        ]);

        // Not mass assignable, and rightly so: Google proving the address is
        // the only reason this account skips the verification email.
        $user->markEmailAsVerified();

        $user->roles()->sync([$sellerRole->id]);

        NewSellerRegistered::dispatch($user->fresh(['city']));

        return $user;
    }

    /**
     * Attach the Google identity to an account that was created by other means.
     */
    private function linkGoogleAccount(User $user, SocialiteUser $googleUser): void
    {
        $changes = [];

        if (! $user->google_id) {
            $changes['google_id'] = $googleUser->getId();
        }

        // Google only hands over an address it has verified itself, so an
        // account that never confirmed its email is confirmed by this sign-in.
        $justVerified = ! $user->hasVerifiedEmail();

        if ($justVerified) {
            $changes['email_verified_at'] = now();
        }

        if ($changes) {
            $user->forceFill($changes)->save();
        }

        if ($justVerified) {
            event(new Verified($user));
            $user->refresh();
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $firstName = array_shift($parts) ?? '';
        $lastName = implode(' ', $parts);

        return [$firstName, $lastName !== '' ? $lastName : $firstName];
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    private function failure(string $messageKey): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => __($messageKey)]);
    }
}
