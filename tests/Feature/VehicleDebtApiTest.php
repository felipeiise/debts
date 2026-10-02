<?php

namespace Tests\Feature;

use App\Application\Resilience\ProviderCircuitBreaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class VehicleDebtApiTest extends TestCase
{
    private function debt(string $type = 'IPVA'): array
    {
        return ['id' => '1', 'type' => $type, 'amount' => '100.00', 'due_date' => now()->subDays(10)->format('Y-m-d')];
    }

    public function test_provider_a_json_success_and_money_string_response(): void
    {
        Http::fake(['provider-a.test/*' => Http::response(['debts' => [$this->debt()]], 200)]);
        $response = $this->postJson('/api/vehicle-debts', ['plate' => 'ABC1234']);
        $response->assertOk()
            ->assertJsonPath('provider', 'provider_a')
            ->assertJsonPath('debts.0.original_amount', '100.00')
            ->assertJsonPath('debts.0.due_date', '2024-04-30')
            ->assertJsonPath('debts.0.days_overdue', 10)
            ->assertJsonPath('debts.0.interest', '3.30');
    }

    public function test_provider_a_failure_falls_back_to_provider_b_xml(): void
    {
        Http::fake([
            'provider-a.test/*' => Http::response('unavailable', 503),
            'provider-b.test/*' => Http::response('<debts><debt><id>b1</id><type>IPVA</type><amount>100.00</amount><due_date>'.now()->addDay()->format('Y-m-d').'</due_date></debt></debts>', 200, ['Content-Type' => 'application/xml']),
        ]);
        $this->postJson('/api/vehicle-debts', ['plate' => 'ABC1234'])->assertOk()->assertJsonPath('provider', 'provider_b');
    }

    public function test_open_provider_circuit_skips_provider_and_uses_next_provider(): void
    {
        $this->app->instance(ProviderCircuitBreaker::class, new class implements ProviderCircuitBreaker {
            public function allows(string $provider): bool
            {
                return $provider !== 'provider_a';
            }

            public function recordSuccess(string $provider): void {}

            public function recordFailure(string $provider): void {}
        });

        Http::fake([
            'provider-b.test/*' => Http::response('<debts/>', 200),
        ]);

        $this->postJson('/api/vehicle-debts', ['plate' => 'ABC1234'])
            ->assertOk()
            ->assertJsonPath('provider', 'provider_b');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'provider-a.test'));
    }

    public function test_empty_xml_debt_list_is_a_successful_zero_balance(): void
    {
        Http::fake([
            'provider-a.test/*' => Http::response('failure', 503),
            'provider-b.test/*' => Http::response('<debts/>', 200),
        ]);
        $this->postJson('/api/vehicle-debts', ['plate' => 'ABC1234'])->assertOk()->assertJsonPath('debts', [])->assertJsonPath('total', '0.00');
    }

    public function test_all_providers_unavailable_returns_503(): void
    {
        Http::fake(['*' => Http::response('unavailable', 503)]);
        $this->postJson('/api/vehicle-debts', ['plate' => 'ABC1234'])->assertStatus(503);
    }

    public function test_invalid_plate_returns_400(): void
    {
        $this->postJson('/api/vehicle-debts', ['plate' => 'BAD'])->assertStatus(400);
    }

    public function test_unknown_provider_debt_type_returns_422(): void
    {
        Http::fake(['provider-a.test/*' => Http::response(['debts' => [$this->debt('LICENSING')]], 200)]);
        $this->postJson('/api/vehicle-debts', ['plate' => 'ABC1234'])->assertStatus(422);
    }

    public function test_rejects_unknown_json_fields(): void
    {
        $this->postJson('/api/vehicle-debts', ['plate' => 'ABC1234', 'unexpected' => true])->assertStatus(400);
    }

    public function test_filters_specific_debt_type_and_preserves_duplicate_type_rows(): void
    {
        Http::fake(['provider-a.test/*' => Http::response(['debts' => [$this->debt('IPVA'), $this->debt('IPVA'), $this->debt('MULTA')]], 200)]);
        $this->postJson('/api/vehicle-debts', ['plate' => 'ABC1234', 'debt_type' => 'SOMENTE_IPVA'])->assertOk()->assertJsonCount(2, 'debts');
    }
}
