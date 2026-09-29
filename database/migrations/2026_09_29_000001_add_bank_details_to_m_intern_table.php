<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mIntern', function (Blueprint $table) {
            $table->string('txtInternBank')->nullable()->after('floatInternSalary');
            $table->string('txtInternBankAccountNumber', 100)->nullable()->after('txtInternBank');
            $table->string('txtInternBankBranch')->nullable()->after('txtInternBankAccountNumber');
            $table->string('txtInternBankAccountName')->nullable()->after('txtInternBankBranch');
        });
    }

    public function down(): void
    {
        Schema::table('mIntern', function (Blueprint $table) {
            $table->dropColumn([
                'txtInternBank',
                'txtInternBankAccountNumber',
                'txtInternBankBranch',
                'txtInternBankAccountName',
            ]);
        });
    }
};
