<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gắn mã Sapo cho Lead chọn sản phẩm. Lead cũ hoặc nhu cầu nhập tay có thể để trống hai mã.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('sapo_product_id')->nullable()->after('requested_product');
            $table->unsignedBigInteger('sapo_variant_id')->nullable()->after('sapo_product_id');

            $table->index('sapo_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['sapo_variant_id']);
            $table->dropColumn(['sapo_product_id', 'sapo_variant_id']);
        });
    }
};
