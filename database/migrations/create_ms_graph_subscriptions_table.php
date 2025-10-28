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
        Schema::create('ms_graph_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('graph_id')->unique();
            $table->text('resource');
            $table->text('notificationUrl');
            $table->datetime('expiration');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ms_graph_subscriptions');
    }
};
