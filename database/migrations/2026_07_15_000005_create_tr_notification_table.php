<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trNotification', function (Blueprint $table) {
            $table->integer('intNotification_ID')->primary();
            $table->integer('intUser_ID');
            $table->string('txtNotificationType', 40);
            $table->string('txtNotificationTitle', 180);
            $table->text('txtNotificationMessage');
            $table->string('txtNotificationLink', 500)->nullable();
            $table->string('txtNotificationFingerprint', 191)->unique();
            $table->timestamp('dtmNotificationRead')->nullable();
            $table->string('txtInsertedBy')->nullable();
            $table->timestamp('dtmInserted')->nullable();
            $table->string('txtUpdatedBy')->nullable();
            $table->timestamp('dtmUpdated')->nullable();
            $table->boolean('bitActive')->default(true);

            $table->foreign('intUser_ID', 'fk_notification_user')
                ->references('intUser_ID')
                ->on('mUser');
            $table->index(['intUser_ID', 'dtmNotificationRead'], 'ix_notification_user_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trNotification');
    }
};
