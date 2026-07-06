<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_graph_laravel_teams_bot_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('azure_user_id')->unique();
            $table->string('upn')->nullable();
            $table->string('display_name')->nullable();
            $table->string('conversation_id')->nullable();
            $table->string('service_url')->nullable();
            $table->string('tenant_id')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('upn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_graph_laravel_teams_bot_conversations');
    }
};
