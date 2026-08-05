<?php

use App\Models\City;
use App\Models\Role;
use App\Services\PickupReferenceGenerator;
use App\Services\ReturnReferenceGenerator;
use App\Services\TrackingNumberGenerator;
use App\Support\StoreContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\StockFixtures;

/**
 * References sit behind global unique indexes while the models carrying them are
 * scoped to a shop. A generator that asks the scoped query cannot see what other
 * vendors already hold: it hands out a reference that is taken and the insert
 * fails — or, for the sequential ones, restarts the numbering at 1 for every new
 * shop and then collides on every single row.
 *
 * Each test stands inside one shop and plants the references in another, which is
 * exactly the blind spot. For the two random generators the candidate space is
 * narrowed by configuration and then filled but for one value, because otherwise
 * a blind generator would still return something different from the single
 * reference we planted and the assertion would pass for the wrong reason.
 *
 * Rows are planted with the query builder rather than through a model: what is
 * under test is the generator, not the aggregates a valid order or return needs.
 */
beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->owner = StockFixtures::user(Role::SELLER);
    $this->here = StockFixtures::store($this->owner, 'Boutique courante');
    $this->elsewhere = StockFixtures::store($this->owner, 'Autre boutique');

    app(StoreContext::class)->enforceFor($this->owner, $this->here->id);
});

/**
 * Plant a parcel in the shop the actor is *not* standing on.
 *
 * @return int Its id.
 */
function orderElsewhere(object $test, string $trackingNumber): int
{
    $city = City::query()->firstOrCreate(
        ['name' => 'Casablanca'],
        ['code' => 'CAS', 'is_active' => true]
    );

    return (int) DB::table('orders')->insertGetId([
        'store_id' => $test->elsewhere->id,
        'seller_id' => $test->owner->id,
        'tracking_number' => $trackingNumber,
        'customer_first_name' => 'Karim',
        'customer_last_name' => 'Alaoui',
        'customer_phone' => '0600000000',
        'customer_address' => 'Rue de Fès, Casablanca',
        'city_id' => $city->id,
    ]);
}

test('a tracking number held by another shop is never handed out again', function () {
    config()->set('orders.company_code', 'SPD');
    config()->set('orders.tracking_random_digits', 1);

    // Nine possible numbers, eight of them already carried by the other shop.
    $free = 'SPD-'.date('Y').'-7';

    foreach (range(1, 9) as $digit) {
        if ($digit !== 7) {
            orderElsewhere($this, 'SPD-'.date('Y').'-'.$digit);
        }
    }

    // Repeated because a blind generator returns its first draw: it would land on
    // the free number by luck one time in nine, and never eight times running.
    foreach (range(1, 8) as $ignored) {
        expect(app(TrackingNumberGenerator::class)->generate())->toBe($free);
    }
});

test('a return reference held by another shop is never handed out again', function () {
    config()->set('returns.reference_prefix', 'RTN');
    config()->set('returns.reference_random_digits', 4);

    $orderId = orderElsewhere($this, 'SPD-'.date('Y').'-000001');

    // The generator draws in 1000…9999. Eight ninths of that range is taken by the
    // other shop, leaving 9000…9999 free — a band rather than a single value so a
    // sighted generator finds a way out in a handful of draws instead of nine
    // thousand, while a blind one still lands in the taken part almost every time.
    foreach (collect(range(1000, 8999))->chunk(1000) as $chunk) {
        DB::table('returns')->insert($chunk->map(fn (int $number) => [
            'store_id' => $this->elsewhere->id,
            'reference' => 'RTN-'.date('Y').'-'.$number,
            'order_id' => $orderId,
            'initiated_by_role' => Role::SELLER,
            'reason' => 'CUSTOMER_REFUSED',
        ])->all());
    }

    foreach (range(1, 5) as $ignored) {
        expect(app(ReturnReferenceGenerator::class)->generate())
            ->toMatch('/^RTN-'.date('Y').'-9\d{3}$/');
    }
});

test('the pickup sequence continues past the references of other shops', function () {
    $first = app(PickupReferenceGenerator::class)->generate();

    DB::table('pickup_requests')->insert([
        'store_id' => $this->elsewhere->id,
        'reference' => $first,
        'created_by' => $this->owner->id,
        'pickup_address' => 'Entrepôt, Casablanca',
    ]);

    // Sequential, so a blind generator restarts at the very reference it just
    // handed out instead of moving past it.
    expect(app(PickupReferenceGenerator::class)->generate())->not->toBe($first);
});
