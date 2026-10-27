<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Request;

enum UiScale: string
{
    case XSmall = '90';
    case Small = '95';
    case Default = '100';
    case Large = '105';
    case XLarge = '110';
    case XXLarge = '117.5';
    case XXXLarge = '125';

    public static function of(Request $request): self
    {
        return array_find(self::cases(), fn (self $scale): bool => $scale->value === $request->cookie('ui_scale')) ?? self::Default;
    }

    public function percentage(): int|float
    {
        return str_contains($this->value, '.') ? (float) $this->value : (int) $this->value;
    }
}
