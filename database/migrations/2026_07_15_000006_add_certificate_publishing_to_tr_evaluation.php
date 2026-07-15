<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trEvaluation', function (Blueprint $table) {
            $table->boolean('bitEvaluationCertificatePublished')->default(false);
            $table->timestamp('dtmEvaluationCertificatePublished')->nullable();
            $table->integer('intEvaluationCertificatePublishedByUser_ID')->nullable();

            $table->foreign('intEvaluationCertificatePublishedByUser_ID', 'fk_evaluation_certificate_publisher')
                ->references('intUser_ID')
                ->on('mUser');
            $table->index('bitEvaluationCertificatePublished', 'ix_evaluation_certificate_published');
        });
    }

    public function down(): void
    {
        Schema::table('trEvaluation', function (Blueprint $table) {
            $table->dropForeign('fk_evaluation_certificate_publisher');
            $table->dropIndex('ix_evaluation_certificate_published');
            $table->dropColumn([
                'bitEvaluationCertificatePublished',
                'dtmEvaluationCertificatePublished',
                'intEvaluationCertificatePublishedByUser_ID',
            ]);
        });
    }
};
