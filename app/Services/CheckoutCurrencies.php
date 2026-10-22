<?php

namespace App\Services;

use App\Services\OxaPay\OxaPayClient;
use App\Services\OxaPay\OxaPayException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Coins the platform's OxaPay account accepts, with their networks, for the
 * custom checkout's coin picker.
 */
class CheckoutCurrencies
{
    public function __construct(protected OxaPayClient $oxapay)
    {
        //
    }

    /**
     * @return array<string, array{symbol: string, name: string, networks: array<string, array{name: string, standard: ?string}>}>
     */
    public function all(): array
    {
        $key = config('oxapay.merchant_api_key');

        if (blank($key)) {
            return [];
        }

        try {
            return Cache::remember('oxapay.checkout_currencies.'.md5($key), now()->addMinutes(10), function () {
                $all = $this->oxapay->currencies();
                $coins = [];

                foreach ($this->oxapay->acceptedCurrencies() as $symbol) {
                    $coin = $all[$symbol] ?? null;

                    if (! $coin || ! ($coin['status'] ?? false) || empty($coin['networks'])) {
                        continue;
                    }

                    $coins[$symbol] = [
                        'symbol' => $symbol,
                        'name' => $coin['name'] ?? $symbol,
                        'networks' => collect($coin['networks'])->map(fn (array $network) => [
                            'name' => $network['name'] ?? $network['network'],
                            // Token standard such as TRC20 / ERC20 / BEP20, if the network has one.
                            'standard' => collect($network['keys'] ?? [])->first(fn ($k) => preg_match('/^[A-Z]{3}\d{2}$/', $k)),
                        ])->all(),
                    ];
                }

                return $coins;
            });
        } catch (OxaPayException $e) {
            Log::warning('Could not load OxaPay currencies', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Validate the customer's coin + network choice. A coin with one network
     * needs no choice; otherwise one must be picked, because sending on the
     * wrong network loses the funds.
     *
     * @return array{0: string, 1: string} [coin, network]
     *
     * @throws ValidationException
     */
    public function resolve(?string $symbol, ?string $network): array
    {
        $coin = $this->all()[$symbol ?? ''] ?? null;

        if (! $coin) {
            throw ValidationException::withMessages(['pay_currency' => 'Choose a coin to pay with.']);
        }

        $networks = array_keys($coin['networks']);

        if (count($networks) === 1) {
            return [$symbol, $networks[0]];
        }

        if (! in_array($network, $networks, true)) {
            throw ValidationException::withMessages(['network' => "Choose the network you'll send {$symbol} on."]);
        }

        return [$symbol, $network];
    }
}
