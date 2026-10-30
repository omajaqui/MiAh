<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_cycle_id')->constrained('savings_cycles')->restrictOnDelete();
            $table->foreignId('savings_turn_id')->constrained('savings_turns')->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->decimal('expected_amount', 15, 2);
            $table->decimal('received_amount', 15, 2)->default(0);
            $table->date('expected_date');
            $table->timestamp('received_at')->nullable();
            $table->string('status', 30);
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->unique(['savings_turn_id', 'person_id'], 'turn_person_contribution_unique');
            $table->index('status');
        });

        Schema::create('savings_contribution_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_contribution_id')->constrained('savings_contributions')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamp('paid_at');
            $table->string('status', 30)->default('registrado');
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->text('annul_reason')->nullable();
            $table->timestamp('annulled_at')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_contribution_payments');
        Schema::dropIfExists('savings_contributions');
    }
};
