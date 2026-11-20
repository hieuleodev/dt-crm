<?php

namespace App\Services\Sapo;

use App\Models\SapoConnection;
use App\Models\SapoProduct;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;
use UnexpectedValueException;

class SapoProductCatalog
{
    private const PAGE_SIZE = 50;

    public function isConfigured(): bool
    {
        return $this->configuredRequest() !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function productsPage(int $page): array
    {
        $request = $this->configuredRequest()
            ?? throw new LogicException('Configure Sapo credentials before syncing.');

        $products = $request
            ->get('/admin/products.json', [
                'limit' => self::PAGE_SIZE,
                'page' => $page,
            ])
            ->throw()
            ->json('products');

        if (! is_array($products)) {
            throw new UnexpectedValueException('Sapo returned an invalid products response.');
        }

        return $products;
    }

    /**
     * Đọc danh mục theo từng trang và cập nhật theo mã biến thể để đồng bộ lại không tạo bản ghi trùng.
     * Mỗi trang đã lưu có thời điểm đồng bộ riêng và vẫn có thể tra cứu nếu trang sau gặp lỗi.
     */
    public function sync(): int
    {
        $page = 1;
        $syncedVariants = 0;

        do {
            $products = $this->productsPage($page);
            $rows = [];
            $syncedAt = now();

            foreach ($products as $product) {
                if (! is_array($product)) {
                    throw new UnexpectedValueException('Sapo returned an invalid product record.');
                }

                $productId = $this->integerId($product['id'] ?? null, 'product');
                $productName = $product['name'] ?? $product['title'] ?? null;
                if (! is_string($productName) || trim($productName) === '') {
                    throw new UnexpectedValueException("Sapo product {$productId} has no name.");
                }

                $variants = $product['variants'] ?? null;
                if (! is_array($variants)) {
                    throw new UnexpectedValueException("Sapo product {$productId} has an invalid variants list.");
                }

                foreach ($variants as $variant) {
                    if (! is_array($variant)) {
                        throw new UnexpectedValueException("Sapo product {$productId} has an invalid variant.");
                    }

                    $variantId = $this->integerId($variant['id'] ?? null, 'variant');
                    $price = $variant['price'] ?? null;
                    if (! is_numeric($price) || (float) $price < 0) {
                        throw new UnexpectedValueException("Sapo variant {$variantId} has an invalid price.");
                    }

                    $variantName = $variant['name'] ?? $variant['title'] ?? null;
                    $variantName = is_string($variantName) ? trim($variantName) : null;
                    $sku = is_string($variant['sku'] ?? null) ? trim($variant['sku']) : null;
                    $rows[] = [
                        'sapo_product_id' => $productId,
                        'sapo_variant_id' => $variantId,
                        'product_name' => trim($productName),
                        'variant_name' => $variantName,
                        'sku' => $sku,
                        'search_text' => SapoProduct::catalogSearchText($productName, $variantName, $sku),
                        'unit_price' => (string) $price,
                        'vendor' => is_string($product['vendor'] ?? null) ? trim($product['vendor']) : null,
                        'product_type' => $this->stringValue($product['product_type'] ?? null, 180),
                        'alias' => $this->stringValue($product['alias'] ?? null, 255),
                        'description' => $this->plainText($product['body_html'] ?? null),
                        'image_url' => $this->imageUrl($product),
                        'product_status' => $this->stringValue($product['status'] ?? null, 50),
                        'tags' => json_encode($this->tags($product['tags'] ?? null), JSON_THROW_ON_ERROR),
                        'synced_at' => $syncedAt,
                        'created_at' => $syncedAt,
                        'updated_at' => $syncedAt,
                    ];
                }
            }

            if ($rows !== []) {
                SapoProduct::query()->upsert(
                    $rows,
                    ['sapo_variant_id'],
                    [
                        'sapo_product_id',
                        'product_name',
                        'variant_name',
                        'sku',
                        'search_text',
                        'unit_price',
                        'vendor',
                        'product_type',
                        'alias',
                        'description',
                        'image_url',
                        'product_status',
                        'tags',
                        'synced_at',
                        'updated_at',
                    ],
                );
                $syncedVariants += count($rows);
            }

            $page++;
            if (count($products) === self::PAGE_SIZE) {
                usleep(500_000);
            }
        } while (count($products) === self::PAGE_SIZE);

        return $syncedVariants;
    }

    private function stringValue(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }

    /**
     * Chuyển mô tả HTML từ Sapo thành văn bản để hiển thị trong ô chọn sản phẩm.
     */
    private function plainText(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $value) ?? $value;
        $value = preg_replace(
            '#<(?:br|/p|/div|/li|/tr|/h[1-6])\b[^>]*>#i',
            "\n",
            $value,
        ) ?? $value;
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function imageUrl(array $product): ?string
    {
        $images = [];
        if (is_array($product['image'] ?? null)) {
            $images[] = $product['image'];
        }
        if (is_array($product['images'] ?? null)) {
            $images = [...$images, ...$product['images']];
        }

        foreach ($images as $image) {
            $url = is_array($image) ? ($image['src'] ?? null) : null;
            if (
                is_string($url)
                && filter_var($url, FILTER_VALIDATE_URL)
                && parse_url($url, PHP_URL_SCHEME) === 'https'
            ) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function tags(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $tags = [];
        foreach ($value as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $tags[] = trim($tag);
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * Chọn Basic Auth cho Private App hoặc header access token cho chế độ OAuth.
     */
    private function configuredRequest(): ?PendingRequest
    {
        $domain = strtolower(trim((string) config('services.sapo.store_domain')));
        if (preg_match('/\A[a-z0-9][a-z0-9.-]*\.mysapo\.net\z/', $domain) !== 1) {
            return null;
        }

        $request = Http::baseUrl("https://{$domain}")
            ->acceptJson()
            ->timeout(30);

        if (config('services.sapo.auth_mode') === 'private_app') {
            $apiKey = (string) config('services.sapo.client_id');
            $apiSecret = (string) config('services.sapo.client_secret');

            return filled($apiKey) && filled($apiSecret)
                ? $request->withoutRedirecting()->withBasicAuth($apiKey, $apiSecret)
                : null;
        }

        if (config('services.sapo.auth_mode') !== 'oauth') {
            return null;
        }

        $token = $this->accessToken($domain);

        return filled($token) ? $request->withHeader('X-Sapo-Access-Token', $token) : null;
    }

    private function accessToken(string $domain): ?string
    {
        $connection = SapoConnection::query()
            ->where('store_domain', $domain)
            ->first();

        // Ưu tiên token OAuth đã lưu cho đúng cửa hàng, sau đó mới dùng token trong cấu hình.
        return $connection?->access_token ?: config('services.sapo.access_token');
    }

    private function integerId(mixed $value, string $resource): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new UnexpectedValueException("Sapo returned an invalid {$resource} ID.");
        }

        return $id;
    }
}
