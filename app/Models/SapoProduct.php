<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Một dòng danh mục CRM lưu một biến thể Sapo để các trang nghiệp vụ tra cứu.
 */
class SapoProduct extends Model
{
    protected $fillable = [
        'sapo_product_id',
        'sapo_variant_id',
        'product_name',
        'variant_name',
        'sku',
        'unit_price',
        'vendor',
        'product_type',
        'alias',
        'description',
        'image_url',
        'product_status',
        'tags',
        'synced_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (SapoProduct $product): void {
            $product->search_text = self::catalogSearchText($product->product_name, $product->variant_name, $product->sku);
        });
    }

    public static function catalogSearchText(string $name, ?string $variant, ?string $sku): string
    {
        return self::normalizeSearchText("{$name} {$variant} {$sku}");
    }

    /**
     * Bỏ dấu trong chữ Latin để tra tên tiếng Việt. Giữ chữ và số của các hệ chữ khác.
     */
    public static function normalizeSearchText(string $text): string
    {
        $text = preg_replace_callback(
            '/\p{Latin}[\p{Latin}\p{M}]*/u',
            static fn (array $match): string => Str::ascii($match[0], 'vi'),
            $text,
        ) ?? $text;

        return trim(preg_replace('/[^\p{L}\p{N}\p{M}]+/u', ' ', mb_strtolower($text)) ?? '');
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'tags' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
