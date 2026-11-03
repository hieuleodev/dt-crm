<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_tasks', function (Blueprint $table) {
            $table->id();

            $table->string('title', 180);
            $table->text('description')->nullable();

            $table->string('type', 30);
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('pending');

            $table->foreignId('assignee_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->restrictOnDelete();

            $table->dateTime('due_at');

            $table->text('result')->nullable();
            $table->text('change_note')->nullable();

            $table->dateTime('completed_at')->nullable();

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->unsignedInteger('version')->default(0);

            $table->timestamps();

            $table->index(['assignee_id', 'status', 'due_at']);
            $table->index(['customer_id', 'status']);
            $table->index(['lead_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_tasks');
    }
};