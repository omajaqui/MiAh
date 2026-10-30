<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_cycle_id')->constrained('savings_cycles')->cascadeOnDelete();
            $table->unsignedInteger('turn_number');
            $table->foreignId('beneficiary_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('savings_cycle_member_id')->constrained('savings_cycle_members')->restrictOnDelete();
            $table->date('expected_contribution_date');
            $table->date('expected_disbursement_date');
            $table->decimal('weekly_quota', 15, 2);
            $table->unsignedInteger('contributors_count');
            $table->decimal('expected_gross_amount', 15, 2);
            $table->decimal('collected_amount', 15, 2)->default(0);
            $table->string('status', 30);
            $table->date('actual_disbursement_date')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->unique(['savings_cycle_id', 'turn_number'], 'cycle_turn_number_unique');
            $table->unique(['savings_cycle_id', 'beneficiary_person_id'], 'cycle_beneficiary_unique');
            $table->index('status');
            $table->index('expected_contribution_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_turns');
    }
};
