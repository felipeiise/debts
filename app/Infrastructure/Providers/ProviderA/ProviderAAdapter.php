<?php

namespace App\Infrastructure\Providers\ProviderA;

use App\Domain\Vehicle\Plate;
use App\Infrastructure\Providers\Contracts\DebtProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ProviderAAdapter implements DebtProvider
{
    public function name(): string
    {
        return 'provider_a';
    }

    public function debts(Plate $plate): array
    {
        $url = config('services.vehicle_debts.provider_a_url');
        if (! $url) {
            throw new RuntimeException('Provider A URL is not configured.');
        }
        $response = Http::acceptJson()->timeout((int) config('services.vehicle_debts.timeout', 3))->retry(2, 150, throw: false)->get($url, ['plate' => $plate->value]);
        if (! $response->successful()) {
            throw new RuntimeException('Provider A returned HTTP '.$response->status());
        }
        $payload = $response->json();
        if (! is_array($payload) || ! isset($payload['debts']) || ! is_array($payload['debts'])) {
            throw new RuntimeException('Provider A returned an invalid JSON payload.');
        }

        return $payload['debts'];
    }
}
