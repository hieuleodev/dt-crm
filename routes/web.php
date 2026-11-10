<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Care\CalendarController;
use App\Http\Controllers\Care\TaskController;
use App\Http\Controllers\Care\WorkbenchController;
use App\Http\Controllers\Customer\ContactController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Customer\CustomerNoteController;
use App\Http\Controllers\Customer\DuplicateCheckController;
use App\Http\Controllers\Customer\PlantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OpportunityController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Imports
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports', [ImportController::class, 'store'])->middleware('throttle:5,1')->name('imports.store');
    Route::get('/imports/templates/{type}/{format}', [ImportController::class, 'template'])
        ->whereIn('type', ['customer', 'lead'])
        ->whereIn('format', ['csv', 'xlsx'])
        ->name('imports.template');

    // Lead
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');

    // Cơ hội kinh doanh
    Route::get('/opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');
    Route::post('/opportunities', [OpportunityController::class, 'store'])->name('opportunities.store');
    Route::put('/opportunities/{opportunity}', [OpportunityController::class, 'update'])->whereNumber('opportunity')->name('opportunities.update');
    Route::delete('/opportunities/{opportunity}', [OpportunityController::class, 'destroy'])->whereNumber('opportunity')->name('opportunities.destroy');
    Route::patch('/opportunities/{opportunity}/stage', [OpportunityController::class, 'updateStage'])->whereNumber('opportunity')->name('opportunities.stage');
    Route::post('/opportunities/{opportunity}/convert-customer', [OpportunityController::class, 'convertToCustomer'])->whereNumber('opportunity')->name('opportunities.convert-customer');

    // Khách hàng
    Route::get('/customers/check-duplicates', DuplicateCheckController::class)->name('customers.check-duplicates');
    Route::resource('customers', CustomerController::class);
    Route::post('/customers/{customer}/reassign', [CustomerController::class, 'reassign'])->name('customers.reassign');
    Route::resource('customers.contacts', ContactController::class)->only(['store', 'update', 'destroy'])->scoped();
    Route::resource('customers.plants', PlantController::class)->only(['store', 'update', 'destroy'])->scoped();

    // Tasks
    Route::get('/tasks/references', [TaskController::class, 'references'])->name('tasks.references');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->whereNumber('task')->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'status'])->whereNumber('task')->name('tasks.status');

    // Calendar
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // Today tasks / Workbench
    Route::get('/workbench', WorkbenchController::class)->name('workbench');

    // Notes customer
    Route::get('/customers/{customer}/notes', [CustomerNoteController::class, 'index'])->name('customers.notes.index');
    Route::post('/customers/{customer}/notes', [CustomerNoteController::class, 'store'])->name('customers.notes.store');
});
