<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mỗi biến thể có một dòng riêng. Mã biến thể duy nhất là khóa cập nhật khi đồng bộ lại.
     */
    public function up(): void
    {
        Schema::create('sapo_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sapo_product_id');
            $table->unsignedBigInteger('sapo_variant_id')->unique();
            $table->string('product_name', 255);
            $table->string('variant_name', 255)->nullable();
            $table->string('sku', 120)->nullable()->index();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('vendor', 180)->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->index(['product_name', 'variant_name']);
            $table->index('sapo_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sapo_products');
    }
};
