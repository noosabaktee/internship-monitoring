<?php

use App\Models\TrWorkFromHomeRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trWorkFromHomeRequest', function (Blueprint $table) {
            $table->string('txtWorkFromHomeRequestType', 20)
                ->default(TrWorkFromHomeRequest::TYPE_WFH)
                ->after('intIntern_ID');
            $table->index(['txtWorkFromHomeRequestType', 'txtWorkFromHomeRequestStatus'], 'ix_wfh_type_status');
        });
    }

    public function down(): void
    {
        Schema::table('trWorkFromHomeRequest', function (Blueprint $table) {
            $table->dropIndex('ix_wfh_type_status');
            $table->dropColumn('txtWorkFromHomeRequestType');
        });
    }
};
