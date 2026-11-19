<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm tên và giá sản phẩm đã ghi nhận trên hồ sơ. Số lượng dùng khi chuyển sang Cơ hội.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('requested_product', 180)->nullable()->after('title');
            $table->decimal('requested_quantity', 12, 3)->nullable()->after('requested_product');
            $table->decimal('requested_unit_price', 15, 2)->nullable()->after('requested_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'requested_product',
                'requested_quantity',
                'requested_unit_price',
            ]);
        });
    }
};
