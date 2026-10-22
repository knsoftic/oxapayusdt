<?php

use App\Support\Money;

if (! function_exists('brand_vars')) {
    /**
     * CSS custom properties for a brand colour, with dark text on light colours
     * so buttons stay readable.
     */
    function brand_vars(?string $color): string
    {
        $color = preg_match('/^#[0-9a-f]{6}$/i', (string) $color) ? $color : '#4f46e5';
        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        $onBrand = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 > 0.62 ? '#0f172a' : '#ffffff';

        return "--brand: {$color}; --on-brand: {$onBrand};";
    }
}

if (! function_exists('usd')) {
    /**
     * "$1,234.50" style label for USD amounts in views.
     */
    function usd(int|float|string|null $amount): string
    {
        return Money::usd($amount);
    }
}
