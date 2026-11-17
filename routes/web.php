<?php

use App\Http\Controllers\StudioAuthController;
use App\Http\Controllers\StudioDashboardController;
use App\Http\Controllers\StudioEmployeeController;
use App\Http\Controllers\StudioTemplateController;
use App\Http\Controllers\StudioAssetController;
use App\Http\Controllers\StudioCardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/studio');

Route::get('/login', [StudioAuthController::class, 'loginForm'])->middleware('guest')->name('login');
Route::post('/login', [StudioAuthController::class, 'login'])->middleware(['guest','throttle:5,1'])->name('studio.login.submit');
Route::get('/studio/login', [StudioAuthController::class, 'loginForm'])->middleware('guest')->name('studio.login');
Route::post('/logout', [StudioAuthController::class, 'logout'])->middleware('auth')->name('studio.logout');
Route::get('/verify-id/{token}', [StudioCardController::class, 'verify'])->middleware('throttle:30,1')->name('studio.verify');

Route::middleware('auth')->prefix('studio')->name('studio.')->group(function (): void {
    Route::get('/', StudioDashboardController::class)->name('dashboard');
    Route::get('/employees', [StudioEmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [StudioEmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [StudioEmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}/edit', [StudioEmployeeController::class, 'edit'])->whereNumber('employee')->name('employees.edit');
    Route::put('/employees/{employee}', [StudioEmployeeController::class, 'update'])->whereNumber('employee')->name('employees.update');
    Route::get('/employees/{employee}/photo', [StudioEmployeeController::class, 'photo'])->whereNumber('employee')->name('employees.photo');
    Route::get('/templates', [StudioTemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [StudioTemplateController::class, 'create'])->name('templates.create');
    Route::get('/templates/{template}/edit', [StudioTemplateController::class, 'edit'])->whereNumber('template')->name('templates.edit');
    Route::put('/templates/{template}/draft', [StudioTemplateController::class, 'save'])->whereNumber('template')->name('templates.save');
    Route::post('/templates/{template}/publish', [StudioTemplateController::class, 'publish'])->whereNumber('template')->name('templates.publish');
    Route::post('/templates/{template}/assets', [StudioTemplateController::class, 'uploadAsset'])->whereNumber('template')->middleware('throttle:15,1')->name('templates.assets');
    Route::post('/templates/{template}/archive', [StudioTemplateController::class, 'archive'])->whereNumber('template')->name('templates.archive');
    Route::get('/assets/{asset}', [StudioAssetController::class, 'show'])->whereNumber('asset')->name('assets.show');
    Route::get('/employees/{employee}/cards', [StudioCardController::class, 'form'])->whereNumber('employee')->name('cards.form');
    Route::post('/employees/{employee}/cards', [StudioCardController::class, 'issue'])->whereNumber('employee')->middleware('throttle:10,1')->name('cards.issue');
    Route::get('/cards/{issue}/print', [StudioCardController::class, 'print'])->whereNumber('issue')->name('cards.print');
    Route::post('/cards/{issue}/revoke', [StudioCardController::class, 'revoke'])->whereNumber('issue')->name('cards.revoke');
    Route::get('/cards/{issue}/photo', [StudioCardController::class, 'issuePhoto'])->whereNumber('issue')->name('cards.photo');
});
