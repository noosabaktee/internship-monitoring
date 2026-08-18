<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trSalarySlip', function (Blueprint $table) {
            $table->integer('intSalarySlip_ID')->primary();
            $table->integer('intIntern_ID');
            $table->integer('intSalarySlipCreatedByUser_ID');
            $table->timestamp('dtmSalarySlipPeriodStart');
            $table->timestamp('dtmSalarySlipPeriodEnd');
            $table->string('txtSalarySlipFileName', 255);
            $table->string('txtSalarySlipFilePath', 500);
            $table->integer('intSalarySlipWorkdays')->default(0);
            $table->integer('intSalarySlipPresentDays')->default(0);
            $table->integer('intSalarySlipLateDays')->default(0);
            $table->integer('intSalarySlipAbsentDays')->default(0);
            $table->integer('intSalarySlipPendingDays')->default(0);
            $table->integer('intSalarySlipPaidDays')->default(0);
            $table->float('floatSalarySlipDailySalary')->default(0);
            $table->float('floatSalarySlipGrossSalary')->default(0);
            $table->float('floatSalarySlipDeduction')->default(0);
            $table->float('floatSalarySlipNetSalary')->default(0);
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();

            $table->foreign('intIntern_ID', 'fk_salary_slip_intern')
                ->references('intIntern_ID')
                ->on('mIntern');
            $table->foreign('intSalarySlipCreatedByUser_ID', 'fk_salary_slip_creator')
                ->references('intUser_ID')
                ->on('mUser');
            $table->index(['intIntern_ID', 'dtmInserted'], 'ix_salary_slip_intern_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trSalarySlip');
    }
};
