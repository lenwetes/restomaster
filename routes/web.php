<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('reservas/crear', [\App\Http\Controllers\ReservaPublicaController::class, 'create'])->name('reservas.publico');
Route::post('reservas/crear', [\App\Http\Controllers\ReservaPublicaController::class, 'store'])->middleware('throttle:10,1');
Route::post('api/reservas', [\App\Http\Controllers\ReservaWebhookController::class, 'crear'])->middleware('throttle:20,1')->name('reservas.webhook');

Route::middleware(['auth'])->group(function () {
    \Livewire\Volt\Volt::route('mesas', 'mesas.index')->middleware('role:mesero,cajero,gerente')->name('mesas');
    \Livewire\Volt\Volt::route('pos', 'pos.terminal')->middleware('role:mesero,cajero,gerente')->name('pos');
    \Livewire\Volt\Volt::route('cocina', 'cocina.kds')->middleware('role:cocina,barra,gerente')->name('cocina');
    \Livewire\Volt\Volt::route('caja', 'caja.control')->middleware('role:cajero,gerente')->name('caja');
    \Livewire\Volt\Volt::route('inventario', 'inventario.index')->middleware('role:gerente')->name('inventario');
    \Livewire\Volt\Volt::route('clientes', 'clientes.index')->middleware('role:cajero,gerente')->name('clientes');
    \Livewire\Volt\Volt::route('delivery', 'delivery.index')->middleware('role:cajero,delivery,repartidor,gerente')->name('delivery');
    \Livewire\Volt\Volt::route('trabajadores', 'trabajadores.index')->middleware('role:admin')->name('trabajadores');
    \Livewire\Volt\Volt::route('menu', 'menu.index')->middleware('role:gerente')->name('menu');
    \Livewire\Volt\Volt::route('reportes', 'reportes.index')->middleware('role:gerente')->name('reportes');
    Route::get('reportes/exportar-pdf', [\App\Http\Controllers\ReporteExportController::class, 'pdf'])->middleware('role:gerente')->name('reportes.pdf');
    Route::get('reportes/exportar-csv', [\App\Http\Controllers\ReporteExportController::class, 'csv'])->middleware('role:gerente')->name('reportes.csv');
    \Livewire\Volt\Volt::route('cxp', 'cxp.index')->middleware('role:gerente')->name('cxp');
    \Livewire\Volt\Volt::route('reservas', 'reservas.index')->middleware('role:mesero,cajero,gerente')->name('reservas');
    \Livewire\Volt\Volt::route('configuracion', 'configuracion.index')->middleware('role:admin')->name('configuracion');
});

require __DIR__.'/auth.php';
