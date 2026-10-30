<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_payment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_cycle_id')->constrained('savings_cycles')->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('status', 40)->default('pendiente_validar');
            $table->text('observations')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['savings_cycle_id', 'status']);
            $table->index(['person_id', 'status']);
        });

        Schema::create('member_payment_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_payment_report_id')->constrained('member_payment_reports')->cascadeOnDelete();
            $table->foreignId('savings_contribution_id')->constrained('savings_contributions')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['member_payment_report_id', 'savings_contribution_id'], 'report_contribution_unique');
        });

        Schema::table('savings_contribution_payments', function (Blueprint $table) {
            $table->foreignId('member_payment_report_id')
                ->nullable()
                ->after('observations')
                ->constrained('member_payment_reports')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('savings_contribution_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_payment_report_id');
        });

        Schema::dropIfExists('member_payment_report_items');
        Schema::dropIfExists('member_payment_reports');
    }
};
