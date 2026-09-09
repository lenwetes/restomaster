<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

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
    \Livewire\Volt\Volt::route('cxp', 'cxp.index')->middleware('role:gerente')->name('cxp');
});

require __DIR__.'/auth.php';
