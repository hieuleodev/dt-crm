<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SapoProduct;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class LeadProducts
{
    /** @return array<string, list<string>> */
    public static function rules(bool $required = false): array
    {
        return [
            'products' => [$required ? 'present' : 'sometimes', 'array', 'list', 'max:50'],
            'products.*' => ['required', 'array:key,name,quantity,unit_price'],
            'products.*.key' => ['required', 'string', 'regex:/\A(?:legacy|[1-9][0-9]{0,19})\z/', 'distinct:strict'],
            'products.*.name' => ['sometimes', 'required', 'string', 'max:180'],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'products.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'products.present' => 'Hãy tải lại danh sách sản phẩm trước khi chuyển Lead.',
            'products.max' => 'Mỗi Lead có thể lưu tối đa 50 dòng sản phẩm.',
            'products.*.key.required' => 'Vui lòng chọn sản phẩm.',
            'products.*.key.distinct' => 'Sản phẩm này đã có trên một dòng khác.',
            'products.*.name.required' => 'Vui lòng nhập tên sản phẩm.',
            'products.*.name.string' => 'Tên sản phẩm không đúng định dạng.',
            'products.*.name.max' => 'Tên sản phẩm không được vượt quá 180 ký tự.',
            'products.*.quantity.*' => 'Số lượng phải là số nguyên dương từ 1 đến 1.000.000.',
            'products.*.unit_price.*' => 'Đơn giá từ 0 đến 9.999.999.999.999,99 ₫ và có tối đa 2 chữ số thập phân.',
        ];
    }

    /**
     * Giữ tên, SKU và giá từng dòng trên hồ sơ khi danh mục Sapo thay đổi.
     *
     * @param  list<array{key: string, name?: string, quantity: string|int|float, unit_price: string|int|float}>  $rows
     * @return array<string, mixed>
     */
    public function selection(?Lead $lead, array $rows): array
    {
        $saved = collect($lead?->products ?? [])->keyBy('key');
        $newKeys = array_filter(array_column($rows, 'key'), fn (string $key): bool => $key !== 'legacy' && ! $saved->has($key));
        $catalog = SapoProduct::query()->whereIn('sapo_variant_id', $newKeys)->get()->keyBy('sapo_variant_id');

        $products = [];
        foreach ($rows as $index => $row) {
            $key = $row['key'];
            $product = $saved->get($key);

            if ($product === null) {
                $variant = $catalog->get($key);
                if ($variant === null) {
                    throw ValidationException::withMessages([
                        "products.{$index}.key" => 'Sản phẩm này không còn trong danh mục Sapo hoặc trên Lead.',
                    ]);
                }
                $product = $this->fromCatalog($variant);
            }

            $products[] = $this->pricedLine($product, $row);
        }

        return $this->attributes($products);
    }

    /**
     * Chỉ chốt giá cho đúng các dòng đã lưu. Kiểm tra sau khi đã khóa Lead để tránh ghi theo form cũ.
     *
     * @param  list<array{key: string, name?: string, quantity: string|int|float, unit_price: string|int|float}>  $rows
     * @return array<string, mixed>
     */
    public function quote(Lead $lead, array $rows): array
    {
        if (array_column($lead->products, 'key') !== array_column($rows, 'key')) {
            throw ValidationException::withMessages(['products' => 'Danh sách sản phẩm đã thay đổi. Hãy mở lại Lead rồi thử lại.']);
        }

        $products = [];
        foreach ($lead->products as $index => $product) {
            $products[] = $this->pricedLine($product, $rows[$index]);
        }

        return $this->attributes($products);
    }

    /** @return array{key: string, sapo_product_id: string, name: string, sku: ?string, quantity: null, unit_price: string} */
    public function fromCatalog(SapoProduct $product): array
    {
        $variantName = $product->variant_name && $product->variant_name !== 'Default Title'
            ? " · {$product->variant_name}"
            : '';

        return [
            'key' => (string) $product->sapo_variant_id,
            'sapo_product_id' => (string) $product->sapo_product_id,
            'name' => mb_substr($product->product_name.$variantName, 0, 180),
            'sku' => $product->sku,
            'quantity' => null,
            'unit_price' => $product->unit_price,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array{name?: string, quantity: string|int|float, unit_price: string|int|float}  $row
     * @return array<string, mixed>
     */
    private function pricedLine(array $product, array $row): array
    {
        return [
            ...$product,
            'name' => $row['name'] ?? $product['name'],
            'quantity' => (string) BigDecimal::of($row['quantity'])->toScale(3),
            'unit_price' => (string) BigDecimal::of($row['unit_price'])->toScale(2),
        ];
    }

    /**
     * Giữ dòng đầu ở các cột cũ để các luồng lưu một sản phẩm vẫn đọc được hồ sơ.
     *
     * @param  list<array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public function attributes(array $products): array
    {
        $first = $products[0] ?? null;
        $attributes = [
            'product_lines' => $products,
            'requested_product' => $first['name'] ?? null,
            'sapo_product_id' => $first['sapo_product_id'] ?? null,
            'sapo_variant_id' => $first && $first['key'] !== 'legacy' ? $first['key'] : null,
            'requested_quantity' => $first['quantity'] ?? null,
            'requested_unit_price' => $first['unit_price'] ?? null,
        ];

        if ($first === null || $first['quantity'] === null) {
            return $attributes;
        }

        $total = BigDecimal::zero();
        foreach ($products as $product) {
            $total = $total->plus(BigDecimal::of($product['quantity'])
                ->multipliedBy($product['unit_price'])->toScale(2, RoundingMode::HalfUp));
        }

        if ($total->isGreaterThan('9999999999999.99')) {
            throw ValidationException::withMessages(['products' => 'Tổng giá báo vượt giới hạn cho phép.']);
        }

        return [...$attributes, 'estimated_value' => (string) $total];
    }
}
