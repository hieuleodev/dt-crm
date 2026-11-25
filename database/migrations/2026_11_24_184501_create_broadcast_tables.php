<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('purpose', 40)->default('repurchase_reminder');
            $table->json('channels');
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_by');
        });

        Schema::create('broadcast_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('broadcast_campaigns')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('cover_image_url', 500)->nullable();
            $table->string('cta_label', 80)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'sort_order']);
        });

        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('broadcast_campaigns')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('recipient_name')->nullable();
            $table->string('recipient_address')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'channel', 'status']);
            $table->index('lead_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcast_articles');
        Schema::dropIfExists('broadcast_campaigns');
    }
};
