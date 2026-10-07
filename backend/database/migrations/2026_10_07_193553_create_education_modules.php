<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educational_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime', 120)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('submission_files', function (Blueprint $table) {
            $table->foreignId('submission_id')->primary()->constrained('submission_references')->restrictOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 120);
        });
        Schema::create('submission_assessments', function (Blueprint $table) {
            $table->foreignId('submission_id')->primary()->constrained('submission_references')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->enum('grade', ['AD', 'A', 'B', 'C']);
            $table->text('feedback');
            $table->timestamps();
        });
        Schema::create('education_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 60);
            $table->unsignedBigInteger('resource_id');
            $table->json('metadata');
            $table->timestamp('created_at');
        });
        Schema::create('education_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('message', 500);
            $table->string('page', 30);
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve educational history; use a reviewed forward repair.');
    }
};
