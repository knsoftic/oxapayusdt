<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Basic format check for a payout address on the chosen network, so obvious
 * mistakes are caught before anything is sent.
 */
class CryptoAddress implements ValidationRule
{
    protected const PATTERNS = [
        'Tron' => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
        'BSC' => '/^0x[0-9a-fA-F]{40}$/',
        'Ethereum' => '/^0x[0-9a-fA-F]{40}$/',
        'Polygon' => '/^0x[0-9a-fA-F]{40}$/',
        'Solana' => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/',
        'The Open Network' => '/^([A-Za-z0-9_-]{48}|-?[01]:[0-9a-fA-F]{64})$/',
    ];

    public function __construct(protected ?string $network)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = self::PATTERNS[$this->network] ?? null;

        if ($pattern && ! preg_match($pattern, trim((string) $value))) {
            $fail("This doesn't look like a valid {$this->network} address.");
        }
    }
}
