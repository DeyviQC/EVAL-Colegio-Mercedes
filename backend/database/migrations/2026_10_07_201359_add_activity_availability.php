<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_settings', function (Blueprint $table) {
            $table->foreignId('activity_id')->primary()->constrained('activity_references')->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->enum('state', ['open', 'closed'])->default('open');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve activity history.');
    }
};
