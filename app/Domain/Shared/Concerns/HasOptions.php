<?php

namespace App\Domain\Shared\Concerns;

trait HasOptions
{
    @return list<array{value: string, label: string}> 
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}