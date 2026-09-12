<?php

use App\Enums\EcommercePlatform;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\EcommerceIntegrationPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-vendor storefront connections, plus one grant per platform so a
     * team member can be allowed to plug YouCan without also seeing Shopify keys.
     */
    public function up(): void
    {
        Schema::create('ecommerce_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('status', 32)->default('pending');
            $table->string('shop_slug')->nullable();
            $table->string('shop_name')->nullable();
            $table->string('external_store_id')->nullable();
            $table->string('client_id')->nullable();
            // Encrypted at rest via the model's "encrypted" cast.
            $table->text('client_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'platform']);
            $table->index(['seller_id', 'platform']);
        });

        $this->seedPlatformPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_integrations');

        Permission::query()
            ->whereIn('name', EcommerceIntegrationPermissions::platformGrants())
            ->delete();
    }

    private function seedPlatformPermissions(): void
    {
        $ids = [];

        foreach (EcommercePlatform::cases() as $platform) {
            $name = EcommerceIntegrationPermissions::manage($platform);

            $ids[] = Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'resource' => 'integrations',
                    'action' => 'manage',
                    'scope' => $platform->value,
                    'type' => 'resource',
                ]
            )->id;
        }

        Role::query()
            ->whereNull('owner_id')
            ->whereIn('name', [...User::SUPER_ADMIN_ROLES, Role::SELLER])
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }
};
