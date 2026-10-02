<?php

namespace App\Application\Vehicle;

use App\Application\Resilience\ProviderCircuitBreaker;
use App\Domain\Debt\Debt;
use App\Domain\Debt\DebtCalculator;
use App\Domain\Debt\DebtType;
use App\Domain\Payment\PaymentSimulator;
use App\Domain\Shared\Money;
use App\Domain\Vehicle\Plate;
use App\Infrastructure\Providers\Contracts\DebtProvider;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use ValueError;

final class GetVehicleDebts
{
    /** @param iterable<DebtProvider> $providers */
    public function __construct(private iterable $providers, private DebtCalculator $calculator, private PaymentSimulator $payments, private ProviderCircuitBreaker $circuitBreaker) {}

    public function execute(Plate $plate, string $filter): array
    {
        $debts = null;
        $providerName = null;
        foreach ($this->providers as $provider) {
            $currentProvider = $provider->name();
            if (! $this->circuitBreaker->allows($currentProvider)) {
                Log::notice('vehicle_debt.provider_circuit_open', [
                    'provider' => $currentProvider,
                    'plate' => $plate->masked(),
                ]);

                continue;
            }

            try {
                $debts = $this->normalize($provider->debts($plate));
                $this->circuitBreaker->recordSuccess($currentProvider);
                $providerName = $currentProvider;
                break;
            } catch (UnknownDebtType $exception) {
                $this->circuitBreaker->recordSuccess($currentProvider);
                throw $exception;
            } catch (Throwable $exception) {
                $this->circuitBreaker->recordFailure($currentProvider);
                Log::warning('vehicle_debt.provider_failed', ['provider' => $currentProvider, 'plate' => $plate->masked(), 'exception' => $exception::class]);
            }
        }
        if ($debts === null) {
            throw new RuntimeException('All debt providers are unavailable.');
        }

        $fixedAsOf = config('services.vehicle_debts.as_of');
        $utc = new DateTimeZone('UTC');
        $asOf = is_string($fixedAsOf) && $fixedAsOf !== ''
            ? (new DateTimeImmutable($fixedAsOf, $utc))->setTimezone($utc)->setTime(0, 0)
            : Date::now('UTC')->startOfDay()->toDateTimeImmutable();
        if (str_starts_with($filter, 'SOMENTE_')) {
            try {
                $only = DebtType::from(substr($filter, 8));
            } catch (ValueError) {
                throw new UnknownDebtType(substr($filter, 8));
            }
            $debts = array_values(array_filter($debts, fn (Debt $debt) => $debt->type === $only));
        }
        $items = [];
        $grandTotal = Money::cents(0);
        foreach ($debts as $debt) {
            $interest = $this->calculator->interest($debt, $asOf);
            $total = $debt->originalAmount->plus($interest);
            $grandTotal = $grandTotal->plus($total);
            $items[] = ['id' => $debt->id, 'type' => $debt->type->value, 'original_amount' => $debt->originalAmount->format(), 'due_date' => $debt->dueDate->format('Y-m-d'), 'days_overdue' => max(0, (int) $debt->dueDate->diff($asOf)->format('%r%a')), 'interest' => $interest->format(), 'total' => $total->format(), 'payment_options' => array_map(fn ($option) => $option->toArray(), $this->payments->simulate($total))];
        }
        Log::info('vehicle_debt.consultation_completed', ['plate' => $plate->masked(), 'provider' => $providerName, 'debt_count' => count($items)]);

        return ['plate' => $plate->value, 'provider' => $providerName, 'debts' => $items, 'total' => $grandTotal->format(), 'payment_options' => array_map(fn ($option) => $option->toArray(), $this->payments->simulate($grandTotal))];
    }

    /** @param array<array-key, mixed> $rows
     * @return list<Debt>
     */
    private function normalize(array $rows): array
    {
        $debts = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['type'], $row['amount'], $row['due_date'])) {
                throw new RuntimeException('Provider returned a malformed debt.');
            }
            if (! is_string($row['amount']) && ! is_int($row['amount'])) {
                throw new RuntimeException('Provider amount must be a decimal string or integer.');
            }
            try {
                $type = DebtType::from(strtoupper((string) $row['type']));
            } catch (ValueError) {
                throw new UnknownDebtType((string) $row['type']);
            }
            $dateString = (string) $row['due_date'];
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateString, new DateTimeZone('UTC'));
            if (! $date || $date->format('Y-m-d') !== $dateString) {
                throw new RuntimeException('Provider returned an invalid due date.');
            }
            try {
                $amount = Money::fromDecimal((string) $row['amount']);
            } catch (\InvalidArgumentException $exception) {
                throw new RuntimeException('Provider returned an invalid amount.', previous: $exception);
            }
            if ($amount->cents < 0) {
                throw new RuntimeException('Provider returned a negative debt amount.');
            }
            $debts[] = new Debt((string) ($row['id'] ?? ''), $type, $amount, $date);
        }

        return $debts;
    }
}
