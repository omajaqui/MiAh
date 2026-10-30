<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_cycle_id')->constrained('savings_cycles')->restrictOnDelete();
            $table->foreignId('savings_turn_id')->constrained('savings_turns')->restrictOnDelete();
            $table->foreignId('beneficiary_person_id')->constrained('people')->restrictOnDelete();
            $table->decimal('expected_amount', 15, 2);
            $table->decimal('collected_amount', 15, 2);
            $table->decimal('delivered_amount', 15, 2);
            $table->timestamp('delivered_at');
            $table->string('status', 30);
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->text('annul_reason')->nullable();
            $table->timestamp('annulled_at')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['savings_turn_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_disbursements');
    }
};
