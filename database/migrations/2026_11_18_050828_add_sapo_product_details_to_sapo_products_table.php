<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm chi tiết từ Sapo cho thẻ xem trước khi chọn sản phẩm trên Lead.
     */
    public function up(): void
    {
        Schema::table('sapo_products', function (Blueprint $table) {
            $table->string('product_type', 180)->nullable();
            $table->string('alias', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->string('product_status', 50)->nullable();
            $table->json('tags')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sapo_products', function (Blueprint $table) {
            $table->dropColumn([
                'product_type',
                'alias',
                'description',
                'image_url',
                'product_status',
                'tags',
            ]);
        });
    }
};
