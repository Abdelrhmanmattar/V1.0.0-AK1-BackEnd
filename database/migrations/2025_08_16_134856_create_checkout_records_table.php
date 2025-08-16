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
        Schema::create('checkout_records', function (Blueprint $table) {
$table->id();

            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->string('part_name');

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('user_name');

            $table->string('custody_assigned_to');
            $table->enum('usage_type', ['internal','external']);

            $table->timestamp('checked_out_at');
            $table->timestamp('returned_at')->nullable();

            $table->json('checkout_photos')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes(); // ✅ soft delete

            $table->index('part_id', 'idx_part_id');
            $table->index('user_id', 'idx_user_id');
            $table->index('usage_type', 'idx_usage_type');
            $table->index('returned_at', 'idx_returned_at');
            $table->index(['part_id','returned_at'], 'idx_part_current');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_records');
    }
};
