<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSapoProductCatalog;
use App\Models\SapoProduct;
use App\Models\User;
use App\Services\LeadProducts;
use App\Services\Sapo\SapoProductCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class SapoProductController extends Controller
{
    /**
     * Tìm trong danh mục đã lưu ở CRM để Lead có thể tra theo tên sản phẩm, biến thể hoặc SKU.
     */
    public function index(Request $request, LeadProducts $leadProducts): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $query = trim($filters['q'] ?? '');

        $productQuery = SapoProduct::query()
            ->orderBy('product_name')
            ->orderBy('variant_name')
            ->limit(40);

        // SKU chính xác dùng chỉ mục hiện có. Tìm theo tên kiểm tra từng từ trên văn bản đã chuẩn hóa.
        $products = $query !== '' && preg_match('/\s/u', $query) === 0
            ? (clone $productQuery)->whereIn('sku', array_unique([$query, mb_strtoupper($query), mb_strtolower($query)]))->get()
            : null;

        if ($products === null || $products->isEmpty()) {
            $normalized = SapoProduct::normalizeSearchText($query);
            if ($query !== '' && $normalized === '') {
                $products = collect();
            } else {
                foreach (array_unique(explode(' ', $normalized)) as $term) {
                    if ($term !== '') {
                        $productQuery->whereLike('search_text', "%{$term}%");
                    }
                }

                $products = $productQuery->get();
            }
        }

        return response()->json([
            'data' => $products->map(fn (SapoProduct $product) => [
                'sapo_product_id' => (string) $product->sapo_product_id,
                'sapo_variant_id' => (string) $product->sapo_variant_id,
                'name' => $leadProducts->fromCatalog($product)['name'],
                'product_name' => $product->product_name,
                'variant_name' => $product->variant_name,
                'sku' => $product->sku,
                'unit_price' => $product->unit_price,
                'vendor' => $product->vendor,
                'product_type' => $product->product_type,
                'description' => $product->description,
                'image_url' => $product->image_url,
                'product_status' => $product->product_status,
                'tags' => $product->tags ?? [],
                'label' => $this->label($product),
            ]),
            // Mốc này là thời điểm cập nhật biến thể gần nhất. Thông báo đồng bộ báo kết quả cả lượt.
            'last_synced_at' => SapoProduct::query()->max('synced_at'),
        ]);
    }

    /**
     * Xếp yêu cầu của quản lý vào hàng đợi. Khóa ngắn ngăn hai lượt bấm cùng tạo job.
     */
    public function sync(Request $request, SapoProductCatalog $catalog): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->isManager(), 403);

        if (! $catalog->isConfigured()) {
            return back()->withErrors([
                'sapo' => 'Kiểm tra SAPO_STORE_DOMAIN, SAPO_AUTH_MODE và thông tin xác thực Sapo trước khi đồng bộ.',
            ]);
        }

        $queued = Cache::store('database')->lock('sapo:queue-catalog-sync', 15)->get(function (): bool {
            if (Queue::connection('sapo')->size() > 0) {
                return false;
            }

            Bus::dispatch(new SyncSapoProductCatalog);

            return true;
        });

        return back()->with('success', $queued
            ? 'Đã xếp lượt đồng bộ Sapo vào hàng đợi.'
            : 'Đã có lượt đồng bộ Sapo đang chờ hoặc đang chạy.');
    }

    public function syncStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->isManager(), 403);

        $queue = Queue::connection('sapo');
        if ($queue->reservedSize() > 0) {
            return response()->json(['state' => 'running', 'message' => 'Đang đồng bộ Sapo']);
        }
        if ($queue->size() > 0) {
            return response()->json(['state' => 'queued', 'message' => 'Đang chờ đồng bộ Sapo']);
        }

        return response()->json(Cache::store('database')->get(SyncSapoProductCatalog::RESULT_CACHE_KEY, [
            'state' => 'idle',
            'message' => null,
        ]));
    }

    private function label(SapoProduct $product): string
    {
        $parts = [$product->product_name];
        if ($product->variant_name && $product->variant_name !== 'Default Title') {
            $parts[] = $product->variant_name;
        }
        if ($product->sku) {
            $parts[] = "SKU {$product->sku}";
        }

        return implode(' · ', $parts);
    }
}
