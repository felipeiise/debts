<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        return require __DIR__.'/../bootstrap/app.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.vehicle_debts.provider_a_url' => 'https://provider-a.test/debts',
            'services.vehicle_debts.provider_b_url' => 'https://provider-b.test/debts',
        ]);
    }
}
