<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ButtonAccessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseAdministrationController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchemaEditorController;
use App\Http\Controllers\SeasonManagementController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\SystemConfigurationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])
        ->middleware('button:workspace.sign_out')
        ->name('logout');
    Route::get('/dashboard', DashboardController::class)
        ->middleware(['role:admin,technician', 'button:dashboard'])
        ->name('dashboard');

    Route::middleware(['role:admin,technician', 'module:farmers'])->group(function () {
        Route::get('/farmers', [FarmerController::class, 'index'])
            ->middleware('button:farmers.list')
            ->name('farmers.index');
    });

    Route::middleware(['role:admin,technician', 'module:farmers', 'button:farmers.create'])->group(function () {
        Route::get('/farmers/create', [FarmerController::class, 'create'])->name('farmers.create');
        Route::post('/farmers', [FarmerController::class, 'store'])->name('farmers.store');
    });

    Route::middleware(['role:admin,technician', 'module:farmers', 'button:farmers.import'])->group(function () {
        Route::get('/farmers/import', [FarmerController::class, 'importForm'])->name('farmers.import');
        Route::get('/farmers/import/template', [FarmerController::class, 'downloadTemplate'])->name('farmers.import.template');
        Route::post('/farmers/import', [FarmerController::class, 'importCsv'])->name('farmers.import.store');
    });

    Route::middleware(['role:admin,technician', 'module:farmers', 'button:farmers.edit'])->group(function () {
        Route::post('/farmers/{farmer}/photo', [FarmerController::class, 'updatePhoto'])->name('farmers.photo.update');
        Route::get('/farmers/{farmer}/edit', [FarmerController::class, 'edit'])->name('farmers.edit');
        Route::put('/farmers/{farmer}', [FarmerController::class, 'update'])->name('farmers.update');
    });

    Route::middleware(['role:admin,technician', 'module:farmers'])->get(
        '/farmers/{farmer}/photo',
        [FarmerController::class, 'photo'],
    )->middleware('button:farmers.view')->name('farmers.photo');
    Route::middleware(['role:admin,technician', 'module:farmers', 'button:farmers.view'])->get(
        '/farmers/{farmer}',
        [FarmerController::class, 'show'],
    )->name('farmers.show');

    Route::middleware(['role:admin,technician', 'module:inventory', 'button:inventory.list'])->get(
        '/supplies',
        [SupplyController::class, 'index'],
    )->name('supplies.index');

    Route::middleware(['role:admin,technician', 'module:inventory'])->group(function () {
        Route::middleware('button:inventory.create')->group(function () {
            Route::resource('supplies', SupplyController::class)->only(['create', 'store']);
        });
        Route::middleware('button:inventory.edit')->group(function () {
            Route::resource('supplies', SupplyController::class)->only(['edit', 'update']);
        });
    });

    Route::middleware(['role:admin,technician', 'module:inventory', 'button:inventory.import'])->group(function () {
        Route::get('/supplies/import', [SupplyController::class, 'importForm'])->name('supplies.import');
        Route::get('/supplies/import/template', [SupplyController::class, 'downloadTemplate'])->name('supplies.import.template');
        Route::post('/supplies/import', [SupplyController::class, 'importCsv'])->name('supplies.import.store');
    });

    Route::middleware(['role:admin,technician', 'module:inventory', 'button:inventory.stock_in'])->post(
        '/supplies/{supply}/stock-in',
        [SupplyController::class, 'stockIn'],
    )->name('supplies.stock-in');

    Route::middleware(['role:admin,technician', 'module:programs', 'button:programs.list'])->get(
        '/programs',
        [ProgramController::class, 'index'],
    )->name('programs.index');

    Route::middleware(['role:admin,technician', 'module:programs', 'button:programs.create'])->group(function () {
        Route::get('/programs/create', [ProgramController::class, 'create'])->name('programs.create');
        Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
    });
    Route::middleware(['role:admin,technician', 'module:programs', 'button:programs.edit'])->group(function () {
        Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])->name('programs.edit');
        Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('programs.update');
    });

    Route::middleware(['role:admin,technician', 'module:seasons', 'button:seasons.list'])->get(
        '/seasons',
        [SeasonManagementController::class, 'index'],
    )->name('seasons.index');

    Route::middleware(['role:admin,technician', 'module:seasons', 'button:seasons.create'])->group(function () {
        Route::get('/seasons/create', [SeasonManagementController::class, 'create'])->name('seasons.create');
        Route::post('/seasons', [SeasonManagementController::class, 'store'])->name('seasons.store');
    });
    Route::middleware(['role:admin,technician', 'module:seasons', 'button:seasons.edit'])->group(function () {
        Route::get('/seasons/{season}/edit', [SeasonManagementController::class, 'edit'])->name('seasons.edit');
        Route::put('/seasons/{season}', [SeasonManagementController::class, 'update'])->name('seasons.update');
    });

    Route::middleware(['role:admin', 'module:users'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->middleware('button:users.list')->name('users.index');
        Route::middleware('button:users.create')->group(function () {
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
        });
        Route::middleware('button:users.edit')->group(function () {
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        });
    });

    Route::middleware(['role:admin', 'module:activity', 'button:activity.list'])->group(function () {
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    });

    Route::middleware(['role:admin,technician', 'module:reports', 'button:reports.view'])->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });

    Route::middleware(['role:admin', 'module:database', 'button:database.view'])->group(function () {
        Route::get('/admin/database', [DatabaseAdministrationController::class, 'index'])->name('database.index');
    });

    Route::middleware(['role:system_admin', 'module:schema_editor'])->group(function () {
        Route::get('/system/schema', [SchemaEditorController::class, 'index'])->name('schema-editor.index');
        Route::post('/system/schema/fields', [SchemaEditorController::class, 'store'])->name('schema-editor.store');
        Route::put('/system/schema/fields/{schemaEditorField}', [SchemaEditorController::class, 'update'])
            ->name('schema-editor.update');
        Route::delete('/system/schema/fields/{schemaEditorField}', [SchemaEditorController::class, 'destroy'])
            ->name('schema-editor.destroy');
    });

    Route::middleware(['role:system_admin', 'module:settings'])->group(function () {
        Route::get('/system/buttons', [ButtonAccessController::class, 'index'])->name('button-access.index');
        Route::put('/system/buttons', [ButtonAccessController::class, 'update'])->name('button-access.update');
    });

    Route::middleware(['role:admin', 'module:settings'])->group(function () {
        Route::get('/settings', [SystemConfigurationController::class, 'index'])->middleware('button:settings.view')->name('settings.index');
        Route::put('/settings', [SystemConfigurationController::class, 'update'])->middleware('button:settings.update')->name('settings.update');
    });

    Route::middleware(['role:admin,technician', 'module:distributions', 'button:distributions.list'])->group(function () {
        Route::get('/distributions', [DistributionController::class, 'index'])->name('distributions.index');
    });

    Route::middleware(['role:admin,technician', 'module:distributions', 'button:distributions.create'])->group(function () {
        Route::get('/distributions/create', [DistributionController::class, 'create'])->name('distributions.create');
        Route::post('/distributions/lookup', [DistributionController::class, 'lookupFarmer'])->name('distributions.lookup');
        Route::post('/distributions', [DistributionController::class, 'store'])->name('distributions.store');
        Route::get('/distributions/farmers/{farmer}/photo', [FarmerController::class, 'photo'])
            ->name('distributions.farmer-photo');
    });

    Route::middleware(['role:admin,technician', 'module:distributions', 'button:distributions.view'])->get(
        '/distributions/{distribution}',
        [DistributionController::class, 'show'],
    )->name('distributions.show');
});
