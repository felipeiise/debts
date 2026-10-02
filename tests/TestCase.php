<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.vehicle_debts.provider_a_url' => 'https://provider-a.test/debts',
            'services.vehicle_debts.provider_b_url' => 'https://provider-b.test/debts',
        ]);
    }
}
