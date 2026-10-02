<?php

use App\Http\Controllers\VehicleDebtController;
use Illuminate\Support\Facades\Route;

Route::post('/vehicle-debts', VehicleDebtController::class)->name('vehicle-debts.consult');
