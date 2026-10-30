<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->unsignedTinyInteger('contribution_weekday');
            $table->date('first_contribution_date');
            $table->date('first_disbursement_date');
            $table->decimal('weekly_quota', 15, 2);
            $table->char('currency', 3)->default('COP');
            $table->unsignedInteger('members_count')->default(0);
            $table->unsignedInteger('turns_count')->default(0);
            $table->string('status', 30);
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('close_reason')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('finalize_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_cycles');
    }
};
