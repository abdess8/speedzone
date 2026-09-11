<?php

namespace App\Actions\Fortify;

use App\Enums\UserStatus;
use App\Events\NewSellerRegistered;
use App\Models\Role;
use App\Models\User;
use App\Support\RegistrationAccountType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered seller or driver account.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'account_type' => ['nullable', 'string', Rule::in(RegistrationAccountType::allowed())],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:30'],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'password' => $this->passwordRules(),
        ])->validate();

        $role = Role::query()
            ->where('name', RegistrationAccountType::roleName($input['account_type'] ?? null))
            ->firstOrFail();

        $user = User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'name' => trim($input['first_name'].' '.$input['last_name']),
            'email' => $input['email'],
            'phone_number' => $input['phone_number'],
            'city_id' => $input['city_id'],
            'password' => Hash::make($input['password']),
            'role_id' => $role->id,
            'status' => UserStatus::PendingEmailVerification,
        ]);

        $user->roles()->sync([$role->id]);

        NewSellerRegistered::dispatch($user->fresh(['city']));

        return $user;
    }
}
