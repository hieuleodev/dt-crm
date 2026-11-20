<?php

use App\Models\SapoProduct;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sapo_products', function (Blueprint $table) {
            $table->text('search_text')->nullable();
            $table->index('synced_at');
        });

        // Chuẩn hóa danh mục hiện có theo lô để có thể tìm ngay sau khi migrate.
        DB::table('sapo_products')
            ->select('id', 'product_name', 'variant_name', 'sku')
            ->chunkById(500, function (Collection $products): void {
                DB::transaction(function () use ($products): void {
                    foreach ($products as $product) {
                        DB::table('sapo_products')
                            ->where('id', $product->id)
                            ->whereNull('search_text')
                            ->update([
                                'search_text' => SapoProduct::catalogSearchText($product->product_name, $product->variant_name, $product->sku),
                            ]);
                    }
                });
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sapo_products', function (Blueprint $table) {
            $table->dropIndex(['synced_at']);
            $table->dropColumn('search_text');
        });
    }
};
