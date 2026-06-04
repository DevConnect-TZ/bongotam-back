<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('user_email')->nullable();
            $table->integer('amount')->default(0);
            $table->string('currency')->default('TSHS');
            $table->string('type')->default('PURCHASE_CONNECTION');
            $table->string('item_id')->nullable();
            $table->string('item_title')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('status')->default('COMPLETED');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
