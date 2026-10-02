<?php

namespace App\Infrastructure\Resilience;

use App\Application\Resilience\ProviderCircuitBreaker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class RedisProviderCircuitBreaker implements ProviderCircuitBreaker
{
    private const ALLOW_SCRIPT = <<<'LUA'
local state = redis.call('HGET', KEYS[1], 'state') or 'closed'
local now = tonumber(ARGV[1])
local probe_lease = tonumber(ARGV[2])

if state == 'open' then
    local open_until = tonumber(redis.call('HGET', KEYS[1], 'open_until') or '0')
    if now < open_until then
        return 0
    end
    state = 'half_open'
end

if state == 'half_open' then
    local probe_until = tonumber(redis.call('HGET', KEYS[1], 'probe_until') or '0')
    if now < probe_until then
        return 0
    end
    redis.call('HSET', KEYS[1], 'state', 'half_open', 'probe_until', now + probe_lease)
    redis.call('PEXPIRE', KEYS[1], probe_lease * 2)
    return 1
end

return 1
LUA;

    private const FAILURE_SCRIPT = <<<'LUA'
local now = tonumber(ARGV[1])
local threshold = tonumber(ARGV[2])
local window = tonumber(ARGV[3])
local cooldown = tonumber(ARGV[4])
local state = redis.call('HGET', KEYS[1], 'state') or 'closed'

if state == 'half_open' then
    redis.call('HSET', KEYS[1], 'state', 'open', 'open_until', now + cooldown, 'probe_until', 0)
    redis.call('PEXPIRE', KEYS[1], cooldown + window)
    return threshold
end

local window_start = tonumber(redis.call('HGET', KEYS[1], 'window_start') or '0')
local failures = tonumber(redis.call('HGET', KEYS[1], 'failures') or '0')
if now - window_start > window then
    failures = 0
    window_start = now
end
failures = failures + 1
redis.call('HSET', KEYS[1], 'state', 'closed', 'window_start', window_start, 'failures', failures)
redis.call('PEXPIRE', KEYS[1], window * 2)

if failures >= threshold then
    redis.call('HSET', KEYS[1], 'state', 'open', 'open_until', now + cooldown)
    redis.call('PEXPIRE', KEYS[1], cooldown + window)
end

return failures
LUA;

    private const SUCCESS_SCRIPT = <<<'LUA'
redis.call('DEL', KEYS[1])
return 1
LUA;

    public function allows(string $provider): bool
    {
        try {
            $now = (int) floor(microtime(true) * 1000);
            $lease = (int) config('services.vehicle_debts.circuit_breaker.probe_lease_seconds', 15) * 1000;

            return (int) $this->evaluate(self::ALLOW_SCRIPT, $provider, [$now, $lease]) === 1;
        } catch (Throwable $exception) {
            Log::warning('vehicle_debt.circuit_breaker_storage_failed', [
                'provider' => $provider,
                'operation' => 'allow',
                'exception' => $exception::class,
            ]);

            // Redis is an optimization for protecting providers. If it fails, keep the normal fallback flow.
            return true;
        }
    }

    public function recordSuccess(string $provider): void
    {
        $this->runScript(self::SUCCESS_SCRIPT, $provider, 'success');
    }

    public function recordFailure(string $provider): void
    {
        try {
            $now = (int) floor(microtime(true) * 1000);
            $threshold = (int) config('services.vehicle_debts.circuit_breaker.failure_threshold', 5);
            $window = (int) config('services.vehicle_debts.circuit_breaker.failure_window_seconds', 30) * 1000;
            $cooldown = (int) config('services.vehicle_debts.circuit_breaker.open_seconds', 30) * 1000;

            $this->evaluate(self::FAILURE_SCRIPT, $provider, [$now, $threshold, $window, $cooldown]);
        } catch (Throwable $exception) {
            $this->logStorageFailure($provider, 'failure', $exception);
        }
    }

    private function runScript(string $script, string $provider, string $operation): void
    {
        try {
            $this->evaluate($script, $provider);
        } catch (Throwable $exception) {
            $this->logStorageFailure($provider, $operation, $exception);
        }
    }

    private function logStorageFailure(string $provider, string $operation, Throwable $exception): void
    {
        Log::warning('vehicle_debt.circuit_breaker_storage_failed', [
            'provider' => $provider,
            'operation' => $operation,
            'exception' => $exception::class,
        ]);
    }

    /** @param list<int> $arguments */
    private function evaluate(string $script, string $provider, array $arguments = []): mixed
    {
        return Redis::connection()->command('eval', [
            $script,
            array_merge([$this->key($provider)], $arguments),
            1,
        ]);
    }

    private function key(string $provider): string
    {
        return 'vehicle-debts:circuit-breaker:'.$provider;
    }
}
