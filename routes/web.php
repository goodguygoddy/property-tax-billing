<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\OutcomeEventController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/clients');

Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');

Route::get('/outcomes/create', [OutcomeEventController::class, 'create'])->name('outcomes.create');
Route::post('/outcomes', [OutcomeEventController::class, 'store'])->name('outcomes.store');
