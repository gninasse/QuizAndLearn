<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 20);
            $table->string('whatsapp', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('organization')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('consent_at');
            $table->text('consent_text');
            $table->string('status')->default('new')->index();
            $table->text('notes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('learner_id')->nullable()->constrained('learners')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('access_request_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_request_id')->constrained('access_requests')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('operator');
            $table->string('reference');
            $table->date('paid_at');
            $table->string('payer_phone', 20);
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('access_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_request_id')->constrained('access_requests')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_request_events');
        Schema::dropIfExists('access_request_payments');
        Schema::dropIfExists('access_requests');
    }
};
