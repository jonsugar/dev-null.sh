<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Request;

enum UiScale: int
{
    case Small = 90;
    case Default = 100;
    case Large = 110;
    case Largest = 125;

    public static function of(Request $request): self
    {
        return array_find(self::cases(), fn (self $scale): bool => (string) $scale->value === $request->cookie('ui_scale')) ?? self::Default;
    }
}
