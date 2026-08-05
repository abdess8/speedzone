<?php

use App\Enums\BillingFrequency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seller billing profile: frequency, payment coordinates (RIB / bank),
     * supporting documents and the automatic-billing toggle + next run date.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_frequency')->default(BillingFrequency::MONTHLY->value)->after('attached_files');
            $table->date('next_billing_date')->nullable()->after('billing_frequency');
            $table->boolean('billing_enabled')->default(false)->after('next_billing_date');

            $table->string('payment_method')->nullable()->after('billing_enabled');
            $table->string('bank_name')->nullable()->after('payment_method');
            $table->string('rib')->nullable()->after('bank_name');

            $table->string('rib_attachment')->nullable()->after('rib');
            $table->string('cin_front_attachment')->nullable()->after('rib_attachment');
            $table->string('cin_back_attachment')->nullable()->after('cin_front_attachment');

            $table->index('billing_enabled');
            $table->index('next_billing_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['billing_enabled']);
            $table->dropIndex(['next_billing_date']);

            $table->dropColumn([
                'billing_frequency',
                'next_billing_date',
                'billing_enabled',
                'payment_method',
                'bank_name',
                'rib',
                'rib_attachment',
                'cin_front_attachment',
                'cin_back_attachment',
            ]);
        });
    }
};
