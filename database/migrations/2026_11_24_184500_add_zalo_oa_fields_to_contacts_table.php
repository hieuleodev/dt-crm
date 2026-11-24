<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('zalo_user_id', 100)->nullable()->after('zalo')->index();
            $table->timestamp('zalo_followed_at')->nullable()->after('zalo_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['zalo_user_id']);
            $table->dropColumn(['zalo_user_id', 'zalo_followed_at']);
        });
    }
};
