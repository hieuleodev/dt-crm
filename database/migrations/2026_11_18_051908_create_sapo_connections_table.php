<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lưu một kết nối OAuth cho mỗi domain. Model SapoConnection mã hóa token khi lưu.
     */
    public function up(): void
    {
        Schema::create('sapo_connections', function (Blueprint $table) {
            $table->id();
            $table->string('store_domain')->unique();
            $table->text('access_token');
            $table->timestamp('connected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sapo_connections');
    }
};
