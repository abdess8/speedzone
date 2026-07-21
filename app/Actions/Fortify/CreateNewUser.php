<?php

namespace App\Actions\Fortify;

use App\Enums\UserStatus;
use App\Events\NewSellerRegistered;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateNewUser implements \Laravel\Fortify\Contracts\CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered seller account.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:30'],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'password' => $this->passwordRules(),
        ])->validate();

        $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

        $user = User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'name' => trim($input['first_name'].' '.$input['last_name']),
            'email' => $input['email'],
            'phone_number' => $input['phone_number'],
            'city_id' => $input['city_id'],
            'password' => Hash::make($input['password']),
            'role_id' => $sellerRole->id,
            'status' => UserStatus::PendingEmailVerification,
        ]);

        $user->roles()->sync([$sellerRole->id]);

        NewSellerRegistered::dispatch($user->fresh(['city']));

        return $user;
    }
}
