<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('mAttendanceSetting') && Schema::hasColumn('mAttendanceSetting', 'txtSettingName')) {
            DB::statement('ALTER TABLE "mAttendanceSetting" ALTER COLUMN "txtSettingName" DROP NOT NULL');
        }

        if (Schema::hasTable('trAttendance') && Schema::hasColumn('trAttendance', 'intIntern_ID')) {
            DB::statement('ALTER TABLE "trAttendance" ALTER COLUMN "intIntern_ID" DROP NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
