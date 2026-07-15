<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trWorkFromHomeRequest', function (Blueprint $table) {
            $table->integer('intWorkFromHomeRequest_ID')->primary();
            $table->integer('intIntern_ID');
            $table->date('dtmWorkFromHomeRequestStartDate');
            $table->date('dtmWorkFromHomeRequestEndDate');
            $table->text('txtWorkFromHomeRequestReason');
            $table->string('txtWorkFromHomeRequestAttachment', 500)->nullable();
            $table->string('txtWorkFromHomeRequestStatus', 20)->default('Pending');
            $table->integer('intApproverUser_ID')->nullable();
            $table->timestamp('dtmWorkFromHomeRequestReviewed')->nullable();
            $table->text('txtWorkFromHomeRequestReviewNote')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();
            $table->boolean('bitActive')->default(true);

            $table->foreign('intIntern_ID', 'fk_wfh_request_intern')
                ->references('intIntern_ID')
                ->on('mIntern');
            $table->foreign('intApproverUser_ID', 'fk_wfh_request_approver')
                ->references('intUser_ID')
                ->on('mUser');
            $table->index(['intIntern_ID', 'txtWorkFromHomeRequestStatus'], 'ix_wfh_intern_status');
            $table->index(['dtmWorkFromHomeRequestStartDate', 'dtmWorkFromHomeRequestEndDate'], 'ix_wfh_date_range');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trWorkFromHomeRequest');
    }
};
