<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $this->actingAs($user = User::factory()->create());

    $this->put('/user/password', [
        'current_password' => 'password',
        'password' => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ]);

    expect(Hash::check('NewPassword123', $user->fresh()->password))->toBeTrue();
});

test('a password that does not meet the policy is rejected', function () {
    $this->actingAs($user = User::factory()->create());

    $response = $this->put('/user/password', [
        'current_password' => 'password',
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response->assertSessionHasErrors('password', errorBag: 'updatePassword');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('current password must be correct', function () {
    $this->actingAs($user = User::factory()->create());

    $response = $this->put('/user/password', [
        'current_password' => 'wrong-password',
        'password' => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ]);

    $response->assertSessionHasErrors();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('new passwords must match', function () {
    $this->actingAs($user = User::factory()->create());

    $response = $this->put('/user/password', [
        'current_password' => 'password',
        'password' => 'NewPassword123',
        'password_confirmation' => 'OtherPassword123',
    ]);

    $response->assertSessionHasErrors();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
