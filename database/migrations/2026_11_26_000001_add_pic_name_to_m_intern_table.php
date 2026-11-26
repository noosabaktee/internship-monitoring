<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mIntern', function (Blueprint $table) {
            $table->string('txtInternPicName')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mIntern', function (Blueprint $table) {
            $table->dropColumn('txtInternPicName');
        });
    }
};
