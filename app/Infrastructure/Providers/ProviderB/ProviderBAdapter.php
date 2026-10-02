<?php

namespace App\Infrastructure\Providers\ProviderB;

use App\Domain\Vehicle\Plate;
use App\Infrastructure\Providers\Contracts\DebtProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

final class ProviderBAdapter implements DebtProvider
{
    public function name(): string
    {
        return 'provider_b';
    }

    public function debts(Plate $plate): array
    {
        $url = config('services.vehicle_debts.provider_b_url');
        if (! $url) {
            throw new RuntimeException('Provider B URL is not configured.');
        }
        $response = Http::accept('application/xml')->timeout((int) config('services.vehicle_debts.timeout', 3))->retry(2, 150, throw: false)->get($url, ['plate' => $plate->value]);
        if (! $response->successful()) {
            throw new RuntimeException('Provider B returned HTTP '.$response->status());
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        if ($xml === false || $xml->getName() !== 'debts') {
            throw new RuntimeException('Provider B returned invalid XML.');
        }
        $debts = [];
        foreach ($xml->debt as $debt) {
            $debts[] = [
                'id' => (string) ($debt->id ?? ''),
                'type' => (string) ($debt->type ?? ''),
                'amount' => (string) ($debt->amount ?? ''),
                'due_date' => (string) ($debt->due_date ?? ''),
            ];
        }

        return $debts;
    }
}
