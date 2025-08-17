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
        Schema::create('parts', function (Blueprint $table) {
             $table->id();

            $table->string('part_number')->index('idx_part_number');
            $table->string('serial_number')->nullable()->unique();
            
            $table->string('qr',512)->unique(); // generated string


            $table->string('name');
            $table->enum('facility_classification', ['general','directDedicated','indirectDedicated','private']);
            $table->enum('facility_type', ['officeFurniture','safetyEquipment','serviceDevices']);
            $table->text('facility_details');
            $table->enum('location', ['egypt','saudi']);

            // Leave department_id as-is (restrictOnDelete)
            $table->foreignId('department_id')->constrained()->restrictOnDelete();

            $table->enum('type', ['furniture','device']);
            $table->string('photo');
            $table->string('invoice_photo')->nullable();
            $table->enum('status', ['available','checked-out'])->default('available');
            $table->enum('condition', ['new','like-new','needs-fix','damaged']);
            $table->string('qr_code')->unique();

            $table->decimal('price', 12, 2);
            $table->decimal('vat', 5, 2);
            $table->decimal('all_vat', 12, 2)->nullable();

            $table->text('notes')->nullable();

            $table->enum('checkup_schedule', ['weekly','almost-daily','bi-weekly','monthly'])->nullable();
            $table->timestamp('last_checkup')->nullable();
            $table->timestamp('next_checkup')->nullable();

            $table->timestamps();
            $table->softDeletes(); // ✅ soft delete

            // Composite indexes
            $table->index(['type','status','department_id'], 'idx_type_status_dept');
            $table->index(['location','type','status'], 'idx_loc_type_status');

            // // DB-level checks (MySQL 8+)
            // $table->check('price >= 0');
            // $table->check('vat >= 0 AND vat <= 100');
            // $table->check('(all_vat IS NULL) OR (all_vat >= 0)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
