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
        Schema::create('door_events', function (Blueprint $table) {
$table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('user_name');
            $table->text('reason')->nullable();

            $table->timestamp('occurred_at'); // renamed from "timestamp" to be clearer

            $table->timestamps();
            $table->softDeletes(); // ✅ soft delete

            $table->index('user_id', 'idx_user_id');
            $table->index('occurred_at', 'idx_occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('door_events');
    }
};
