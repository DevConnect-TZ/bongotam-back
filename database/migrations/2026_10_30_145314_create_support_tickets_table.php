<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('language', 2)->default('en');
            $table->text('message');
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('video_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('media_error_code')->nullable();
            $table->unsignedInteger('playback_seconds')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('reply')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
