<?php

use App\Enums\PartnerAuthType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-partner authentication strategy, login endpoint, and cached bearer token
 * obtained after a successful login (e.g. Sendit POST /api/v1/login).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('auth_type')->default(PartnerAuthType::BASIC->value)->after('client_secret');
            $table->string('endpoint_login')->nullable()->after('endpoint_update');
            $table->string('api_key_header')->default('X-API-Key')->after('endpoint_login');
            $table->string('login_username_field')->default('email')->after('api_key_header');
            $table->string('login_password_field')->default('password')->after('login_username_field');
            // Encrypted at rest via the model's "encrypted" cast.
            $table->text('access_token')->nullable()->after('login_password_field');
            $table->timestamp('token_expires_at')->nullable()->after('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'auth_type',
                'endpoint_login',
                'api_key_header',
                'login_username_field',
                'login_password_field',
                'access_token',
                'token_expires_at',
            ]);
        });
    }
};
