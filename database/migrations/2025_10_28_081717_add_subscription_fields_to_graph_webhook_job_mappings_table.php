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
        Schema::table('ms_graph_laravel_webhook_job_mappings', function (Blueprint $table) {
            $table->string('name')->nullable()->after('webhook_type');
            $table->string('filepath')->nullable()->after('job_class');
            $table->string('upn')->nullable()->after('filepath');
            $table->text('resource')->nullable()->after('upn');
            $table->string('notification_url')->nullable()->after('resource');
            $table->string('change_type')->nullable()->after('notification_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ms_graph_laravel_webhook_job_mappings', function (Blueprint $table) {
            $table->dropColumn(['name', 'filepath', 'upn', 'resource', 'notification_url', 'change_type']);
        });
    }
};
