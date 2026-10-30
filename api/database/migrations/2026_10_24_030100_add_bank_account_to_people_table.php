<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('bank_type_id')->nullable()->after('address')->constrained('bank_types')->restrictOnDelete();
            $table->string('account_number', 40)->nullable()->after('bank_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_type_id');
            $table->dropColumn('account_number');
        });
    }
};
