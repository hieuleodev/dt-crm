<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('status')->index();
        });

        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('seen_at')->nullable()->after('delivered_at');
        });

        Schema::create('zalo_oa_connections', function (Blueprint $table) {
            $table->id();
            $table->string('oa_id', 64)->unique();
            $table->string('oa_name')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zalo_oa_connections');

        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'seen_at']);
        });

        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->dropIndex(['scheduled_at']);
            $table->dropColumn('scheduled_at');
        });
    }
};
