<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Date;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNowAndTimezone('2024-05-10T00:00:00Z', 'UTC');
        config([
            'services.vehicle_debts.provider_a_url' => 'https://provider-a.test/debts',
            'services.vehicle_debts.provider_b_url' => 'https://provider-b.test/debts',
        ]);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }
}
