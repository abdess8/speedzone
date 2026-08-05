<?php

use App\Enums\UserStatus;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function activeSeller(array $attributes = []): User
{
    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    $user = User::factory()->create(array_merge([
        'role_id' => $sellerRole->id,
        'status' => UserStatus::Active,
        'email_verified_at' => now(),
        'phone_number' => null,
        'city_id' => null,
        'address' => null,
        'pickup_address_1' => null,
        'pickup_address_2' => null,
        'cin' => null,
        'ice_number' => null,
        'bank_name' => null,
        'rib' => null,
        'photo' => null,
        'profile_photo_path' => null,
    ], $attributes));

    $user->roles()->sync([$sellerRole->id]);

    return $user;
}

test('an empty seller profile scores zero and lists every missing field', function () {
    $completion = activeSeller()->profileCompletion();

    expect($completion['score'])->toBe(0)
        ->and($completion['filled_count'])->toBe(0)
        ->and($completion['is_complete'])->toBeFalse()
        ->and($completion['missing'])->toHaveCount(13);
});

test('every filled field raises the score to one hundred', function () {
    $city = City::query()->create([
        'name' => 'Score City',
        'code' => 'SCR',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $completion = activeSeller([
        'phone_number' => '+212600000000',
        'city_id' => $city->id,
        'address' => 'Rue 1',
        'pickup_address_1' => 'Depot 1',
        'pickup_address_2' => 'Depot 2',
        'cin' => 'AB12345',
        'ice_number' => '001122334455667',
        'bank_name' => 'Bank Al Maghrib',
        'rib' => '011780000012345678901234',
        'rib_attachment' => 'users/billing/rib.pdf',
        'cin_front_attachment' => 'users/billing/front.jpg',
        'cin_back_attachment' => 'users/billing/back.jpg',
        'profile_photo_path' => 'profile-photos/me.jpg',
    ])->profileCompletion();

    expect($completion['score'])->toBe(100)
        ->and($completion['is_complete'])->toBeTrue()
        ->and($completion['missing'])->toBeEmpty();
});

test('the score travels with the shared inertia props', function () {
    $seller = activeSeller(['phone_number' => '+212600000000']);

    $this->actingAs($seller)
        ->get(route('profile.show'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.profile_completion.score', 10)
            ->where('auth.user.profile_completion.is_complete', false)
        );
});

test('a seller saves his details and documents from the profile screen', function () {
    Storage::fake('public');

    $seller = activeSeller();

    $response = $this->actingAs($seller)->put(route('user-seller-profile.update'), [
        'cin' => 'AB12345',
        'ice_number' => '001122334455667',
        'pickup_address_1' => 'Depot Casablanca',
        'pickup_address_2' => 'Depot Rabat',
        'bank_name' => 'Bank Al Maghrib',
        'rib' => '011780000012345678901234',
        'rib_attachment' => UploadedFile::fake()->create('rib.pdf', 100, 'application/pdf'),
        'cin_front_attachment' => UploadedFile::fake()->image('front.jpg'),
        'cin_back_attachment' => UploadedFile::fake()->image('back.jpg'),
    ]);

    $response->assertSessionHasNoErrors();

    $seller->refresh();

    expect($seller->cin)->toBe('AB12345')
        ->and($seller->pickup_address_1)->toBe('Depot Casablanca')
        ->and($seller->rib_attachment)->not->toBeNull()
        ->and($seller->cin_front_attachment)->not->toBeNull()
        ->and($seller->cin_back_attachment)->not->toBeNull()
        ->and($seller->rib_attachment_url)->toStartWith('/storage/');

    Storage::disk('public')->assertExists($seller->rib_attachment);
    Storage::disk('public')->assertExists($seller->cin_front_attachment);
    Storage::disk('public')->assertExists($seller->cin_back_attachment);
});

test('saving without a new file keeps the document already uploaded', function () {
    Storage::fake('public');

    $seller = activeSeller(['rib_attachment' => 'users/billing/existing.pdf']);

    $this->actingAs($seller)->put(route('user-seller-profile.update'), [
        'cin' => 'AB12345',
    ]);

    expect($seller->fresh()->rib_attachment)->toBe('users/billing/existing.pdf');
});

test('a document can be deleted on its own', function () {
    Storage::fake('public');

    $seller = activeSeller([
        'rib_attachment' => UploadedFile::fake()->create('rib.pdf', 10)->store('users/billing', 'public'),
    ]);

    $this->actingAs($seller)
        ->delete(route('user-seller-profile.documents.destroy', 'rib_attachment'));

    expect($seller->fresh()->rib_attachment)->toBeNull();
});

test('an unknown document name is a 404 rather than a wildcard delete', function () {
    $seller = activeSeller();

    $this->actingAs($seller)
        ->delete(route('user-seller-profile.documents.destroy', 'password'))
        ->assertNotFound();
});
