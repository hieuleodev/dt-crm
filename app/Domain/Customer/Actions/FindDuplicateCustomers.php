<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\Contact;
use App\Domain\Customer\Models\Customer;
use Illuminate\Support\Str;

class FindDuplicateCustomers
{
    private const LEGAL_WORDS = [
        'cong ty', 'cty', 'ct', 'trach nhiem huu han', 'tnhh', 'co phan', 'cp', 'mot thanh vien', 'mtv',
        'viet nam', 'vietnam', 'vn', 'co ltd', 'ltd', 'limited', 'company', 'inc', 'jsc', 'corporation', 'corp',
    ];

     @return list<array{id: int, code: string, name: string, owner: ?string, reasons: list<string>}> 
    public function handle(?string $taxCode, ?string $name, ?string $phone, ?string $email, ?int $exceptId = null): array
    {
        $found = [];
        $add = function (Customer $c, string $reason) use (&$found) {
            $found[$c->id] ??= ['id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'owner' => $c->owner?->name, 'reasons' => []];
            $found[$c->id]['reasons'][] = $reason;
        };
        $base = fn () => Customer::query()->with('owner:id,name')->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId));

        if ($tax = Customer::normalizeTaxCode($taxCode)) {
            $base()->where('tax_code', $tax)->get()->each(fn ($c) => $add($c, 'Trùng mã số thuế'));
        }

        if ($phone = preg_replace('/\D/', '', (string) $phone)) {
            $ids = Contact::where('phone', 'like', "%{$phone}%")->pluck('customer_id');
            $base()->where(fn ($q) => $q->where('phone', 'like', "%{$phone}%")->orWhereIn('id', $ids))
                ->get()->each(fn ($c) => $add($c, 'Trùng số điện thoại'));
        }

        if ($email = Str::lower(trim((string) $email))) {
            $ids = Contact::where('email', $email)->pluck('customer_id');
            $base()->where(fn ($q) => $q->where('email', $email)->orWhereIn('id', $ids))
                ->get()->each(fn ($c) => $add($c, 'Trùng email'));
        }

        $key = self::nameKey($name);
        if (mb_strlen($key) >= 3) {
            $base()->where('name_key', $key)->get()->each(fn ($c) => $add($c, 'Tên gần giống'));
        }

        return array_values($found);
    }

    public static function nameKey(?string $name): string
    {
        $s = ' '.Str::lower(Str::ascii((string) $name)).' ';
        $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
        foreach (self::LEGAL_WORDS as $w) {
            $s = str_replace(' '.$w.' ', ' ', $s);
        }

        return trim(preg_replace('/\s+/', ' ', $s));
    }
}