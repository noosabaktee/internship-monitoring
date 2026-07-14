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
            if (! Schema::hasColumn('mIntern', 'txtInternType')) {
                $table->string('txtInternType')->nullable()->default('digitalisasi')->after('txtDept');
            }

            if (! Schema::hasColumn('mIntern', 'floatInternSalary')) {
                $table->float('floatInternSalary')->nullable()->default(0)->after('txtInternType');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mIntern', function (Blueprint $table) {
            if (Schema::hasColumn('mIntern', 'floatInternSalary')) {
                $table->dropColumn('floatInternSalary');
            }

            if (Schema::hasColumn('mIntern', 'txtInternType')) {
                $table->dropColumn('txtInternType');
            }
        });
    }
};
