<?php

namespace App\Http\Controllers;

use App\Application\Vehicle\GetVehicleDebts;
use App\Application\Vehicle\UnknownDebtType;
use App\Domain\Vehicle\Plate;
use App\Domain\Debt\DebtType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class VehicleDebtController
{
    public function __invoke(Request $request, GetVehicleDebts $consultation): JsonResponse
    {
        if (! $request->isJson()) return response()->json(['error' => ['code' => 'content_type_required', 'message' => 'Content-Type must be application/json.']], 400);
        $payload = $request->json()->all();
        $unknown = array_diff(array_keys($payload), ['plate', 'debt_type']);
        if ($unknown !== []) return response()->json(['error' => ['code' => 'unknown_fields', 'message' => 'Unknown request fields.', 'fields' => array_values($unknown)]], 400);
        if (! isset($payload['plate']) || ! is_string($payload['plate'])) return response()->json(['error' => ['code' => 'invalid_plate', 'message' => 'A valid plate is required.']], 400);
        try { $plate = Plate::from($payload['plate']); }
        catch (InvalidArgumentException $exception) { return response()->json(['error' => ['code' => 'invalid_plate', 'message' => $exception->getMessage()]], 400); }
        $filter = strtoupper((string) ($payload['debt_type'] ?? 'TOTAL'));
        if ($filter !== 'TOTAL' && ! preg_match('/^SOMENTE_[A-Z]+$/', $filter)) {
            return response()->json(['error' => ['code' => 'invalid_debt_type', 'message' => 'Use TOTAL or SOMENTE_<TIPO>.']], 422);
        }
        if (str_starts_with($filter, 'SOMENTE_')) {
            try { DebtType::from(substr($filter, 8)); }
            catch (\ValueError) { return response()->json(['error' => ['code' => 'unknown_debt_type', 'message' => 'Unknown debt type: '.substr($filter, 8)]], 422); }
        }
        try {
            return response()->json($consultation->execute($plate, $filter));
        } catch (UnknownDebtType $exception) {
            return response()->json(['error' => ['code' => 'unknown_debt_type', 'message' => 'Unknown debt type: '.$exception->getMessage()]], 422);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'All debt providers are unavailable.') {
                Log::error('vehicle_debt.all_providers_failed', ['plate' => $plate->masked()]);
                return response()->json(['error' => ['code' => 'providers_unavailable', 'message' => 'Debt providers are temporarily unavailable.']], 503);
            }
            Log::error('vehicle_debt.processing_failed', ['plate' => $plate->masked(), 'exception' => $exception::class]);
            return response()->json(['error' => ['code' => 'invalid_provider_data', 'message' => 'A provider returned invalid debt data.']], 502);
        } catch (Throwable $exception) {
            Log::error('vehicle_debt.unexpected_failure', ['plate' => $plate->masked(), 'exception' => $exception::class]);
            return response()->json(['error' => ['code' => 'internal_error', 'message' => 'An unexpected error occurred.']], 500);
        }
    }
}
