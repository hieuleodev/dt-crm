<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tax_id',
        'hotline',
        'email',
        'address',
        'website',
        'representative',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'logo_url',
        'footer_quote_note',
    ];

    /**
     * Get the singleton company setting record.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'name' => null,
            'tax_id' => null,
            'hotline' => null,
            'email' => null,
            'address' => null,
            'website' => null,
            'representative' => null,
            'bank_name' => null,
            'bank_account_number' => null,
            'bank_account_holder' => null,
            'footer_quote_note' => null,
        ]);
    }
}
