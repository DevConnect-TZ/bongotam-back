<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('zone')->nullable()->after('type');
            $table->string('provider')->nullable()->after('status');
            $table->string('payment_status')->nullable()->after('provider');
            $table->string('reference')->nullable()->after('payment_status');
            $table->string('buyer_name')->nullable()->after('reference');
            $table->string('buyer_phone')->nullable()->after('buyer_name');
            $table->string('provider_transaction_id')->nullable()->after('buyer_phone');
            $table->string('channel')->nullable()->after('provider_transaction_id');
            $table->string('msisdn')->nullable()->after('channel');
            $table->string('provider_event')->nullable()->after('msisdn');
            $table->json('provider_payload')->nullable()->after('provider_event');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropColumn([
                'zone',
                'provider',
                'payment_status',
                'reference',
                'buyer_name',
                'buyer_phone',
                'provider_transaction_id',
                'channel',
                'msisdn',
                'provider_event',
                'provider_payload',
            ]);
        });
    }
};
