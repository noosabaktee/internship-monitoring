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
        Schema::table('mIntern', function (Blueprint $table) {
            if (! Schema::hasColumn('mIntern', 'txtInternCostCenter')) {
                $table->string('txtInternCostCenter')->nullable()->after('txtDept');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mIntern', function (Blueprint $table) {
            if (Schema::hasColumn('mIntern', 'txtInternCostCenter')) {
                $table->dropColumn('txtInternCostCenter');
            }
        });
    }
};
