<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('original_name')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('conversion_status', 20)->nullable();
            $table->text('conversion_error')->nullable();
            $table->foreignId('video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->string('url', 2048)->nullable();
            $table->boolean('allow_download')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('group_resource', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->primary(['group_id', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_resource');
        Schema::dropIfExists('resources');
    }
};
