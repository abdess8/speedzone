<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('payment_method', 'COD')
            ->update(['payment_method' => 'CARD_PAYMENT']);
    }

    public function down(): void
    {
        DB::table('orders')
            ->where('payment_method', 'CARD_PAYMENT')
            ->update(['payment_method' => 'COD']);
    }
};
