<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:super_admin,admin,staff')->group(function () {
        Route::get('/farmers/import', [FarmerController::class, 'importForm'])->name('farmers.import');
        Route::get('/farmers/import/template', [FarmerController::class, 'downloadTemplate'])->name('farmers.import.template');
        Route::post('/farmers/import', [FarmerController::class, 'importCsv'])->name('farmers.import.store');
        Route::get('/farmers/{farmer}/photo', [FarmerController::class, 'photo'])->name('farmers.photo');
        Route::post('/farmers/{farmer}/photo', [FarmerController::class, 'updatePhoto'])->name('farmers.photo.update');
        Route::resource('farmers', FarmerController::class)->only([
            'index', 'create', 'store', 'show', 'edit', 'update',
        ]);
    });

    Route::middleware('role:super_admin,admin,supply_officer,custodian')->group(function () {
        Route::resource('supplies', SupplyController::class)->only([
            'index', 'create', 'store', 'edit', 'update',
        ]);
        Route::post('/supplies/{supply}/stock-in', [SupplyController::class, 'stockIn'])->name('supplies.stock-in');
    });

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::resource('programs', ProgramController::class)->only([
            'index', 'create', 'store', 'edit', 'update',
        ]);
        Route::resource('users', UserController::class)->only([
            'index', 'create', 'store', 'edit', 'update',
        ]);
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    });

    Route::middleware('role:super_admin,admin,staff')->group(function () {
        Route::get('/distributions', [DistributionController::class, 'index'])->name('distributions.index');
        Route::get('/distributions/create', [DistributionController::class, 'create'])->name('distributions.create');
        Route::post('/distributions/lookup', [DistributionController::class, 'lookupFarmer'])->name('distributions.lookup');
        Route::post('/distributions', [DistributionController::class, 'store'])->name('distributions.store');
        Route::get('/distributions/{distribution}', [DistributionController::class, 'show'])->name('distributions.show');
    });
});
