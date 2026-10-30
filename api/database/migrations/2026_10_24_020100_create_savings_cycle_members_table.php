<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_cycle_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_cycle_id')->constrained('savings_cycles')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->timestamp('joined_at');
            $table->unsignedInteger('delivery_position');
            $table->string('status', 30)->default('activo');
            $table->decimal('weekly_quota', 15, 2);
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->unique(['savings_cycle_id', 'person_id'], 'cycle_person_unique');
            $table->unique(['savings_cycle_id', 'delivery_position'], 'cycle_position_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_cycle_members');
    }
};
