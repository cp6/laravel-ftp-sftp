<?php

use App\Http\Controllers\ConnectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/connections', [ConnectionController::class, 'index'])->name('connection.index');
    Route::get('/connections/create', [ConnectionController::class, 'create'])->name('connection.create');
    Route::post('/connections', [ConnectionController::class, 'store'])->name('connection.store');
    Route::get('/connections/{connection}/edit', [ConnectionController::class, 'edit'])->name('connection.edit');
    Route::patch('/connections/{connection}', [ConnectionController::class, 'update'])->name('connection.update');
    Route::delete('/connections/{connection}', [ConnectionController::class, 'destroy'])->name('connection.destroy');

    Route::get('/connections', [ConnectionController::class, 'index'])->name('profile.edit');
});

require __DIR__ . '/auth.php';
