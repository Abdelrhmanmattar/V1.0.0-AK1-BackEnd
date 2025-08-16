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
        Schema::create('checkup_records', function (Blueprint $table) {
 $table->id();

            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->string('part_name');

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('user_name');

            $table->timestamp('checkup_date');
            $table->enum('status', ['good','needs-attention','needs-repair']);
            $table->text('notes')->nullable();
            $table->timestamp('next_checkup_date');
            $table->json('checkup_photos')->nullable();

            $table->timestamps();
            $table->softDeletes(); // ✅ soft delete

            $table->index('part_id', 'idx_part_id');
            $table->index('checkup_date', 'idx_checkup_date');
            $table->index('status', 'idx_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkup_records');
    }
};
