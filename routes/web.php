<?php

use App\Http\Controllers\Cliente\AuthClienteController;
use App\Http\Controllers\Cliente\PerfilClienteController;
use App\Http\Controllers\CrmWebhookController;
use App\Http\Controllers\EncuestaPublicaController;
use App\Http\Controllers\ReporteExportController;
use App\Http\Controllers\ReservaPublicaController;
use App\Http\Controllers\ReservaWebhookController;
use App\Http\Middleware\AuthCliente;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::get('dashboard', function () {
    /** @var User|null $user */
    $user = Auth::user();
    if ($user?->role?->slug === 'mesero') {
        return redirect()->route('pos');
    }
    if (in_array($user?->role?->slug, ['cocina', 'barra'], true)) {
        return redirect()->route('cocina');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('reservas/crear', [ReservaPublicaController::class, 'create'])->name('reservas.publico');
Route::post('reservas/crear', [ReservaPublicaController::class, 'store'])->middleware('throttle:10,1');
Route::post('api/reservas', [ReservaWebhookController::class, 'crear'])->middleware('throttle:20,1')->name('reservas.webhook');

// Menú público interactivo para autoservicio por código QR en mesa
Route::get('m/{numero}', function ($numero) {
    return redirect()->route('mesa.menu', ['numero' => $numero]);
})->name('mesa.qr.short');
Volt::route('mesa/{numero}/menu', 'mesa.menu-publico')->middleware('throttle:30,1')->name('mesa.menu');

// Servicios públicos: Delivery en línea y Carta general
Volt::route('delivery/pedir', 'delivery.pedido-publico')->middleware('throttle:30,1')->name('delivery.publico');
Volt::route('carta', 'menu.carta-publica')->middleware('throttle:60,1')->name('carta.publico');

Route::middleware(['auth'])->group(function () {
    Volt::route('mesas', 'mesas.index')->middleware('role:mesero,cajero,gerente')->name('mesas');
    Volt::route('pos', 'pos.terminal')->middleware('role:mesero,cajero,gerente')->name('pos');
    Volt::route('cocina', 'cocina.kds')->middleware('role:admin,gerente,cocina,barra,cajero')->name('cocina');
    Volt::route('caja', 'caja.control')->middleware('role:cajero,gerente')->name('caja');
    Volt::route('inventario', 'inventario.index')->middleware('role:gerente,cajero')->name('inventario');
    Volt::route('clientes', 'clientes.index')->middleware('role:cajero,gerente')->name('clientes');
    Volt::route('crm', 'crm.index')->middleware('role:cajero,gerente,admin')->name('crm');
    Volt::route('delivery', 'delivery.index')->middleware('role:cajero,delivery,repartidor,gerente')->name('delivery');
    Volt::route('trabajadores', 'trabajadores.index')->middleware('role:admin')->name('trabajadores');
    Volt::route('menu', 'menu.index')->middleware('role:gerente,admin')->name('menu');
    Volt::route('reportes', 'reportes.index')->middleware('role:gerente')->name('reportes');
    Route::get('reportes/exportar-pdf', [ReporteExportController::class, 'pdf'])->middleware('role:gerente')->name('reportes.pdf');
    Route::get('reportes/exportar-csv', [ReporteExportController::class, 'csv'])->middleware('role:gerente')->name('reportes.csv');
    Volt::route('cxp', 'cxp.index')->middleware('role:gerente')->name('cxp');
    Volt::route('proveedores', 'proveedores.index')->middleware('role:gerente,admin')->name('proveedores');
    Volt::route('reservas', 'reservas.index')->middleware('role:mesero,cajero,gerente')->name('reservas');
    Volt::route('configuracion', 'configuracion.index')->middleware('role:admin')->name('configuracion');
    Volt::route('impresion', 'impresion.index')->middleware('role:gerente,admin')->name('impresion');
});

// Webhook Meta WhatsApp Cloud API (CRM Automatizaciones)
Route::get('api/webhooks/whatsapp', [CrmWebhookController::class, 'verificar'])->name('crm.webhook.verificar');
Route::post('api/webhooks/whatsapp', [CrmWebhookController::class, 'recibir'])->name('crm.webhook.recibir');

// Portal Público de Clientes (F7-06)
Route::prefix('cliente')->name('cliente.')->group(function () {
    Route::get('login', [AuthClienteController::class, 'showLogin'])->name('login');
    Route::get('auth/{provider}', [AuthClienteController::class, 'redirectToGoogle'])->name('auth.provider');
    Route::get('auth/{provider}/callback', [AuthClienteController::class, 'handleGoogleCallback'])->name('auth.callback');
    Route::post('magic-send', [AuthClienteController::class, 'sendMagicLink'])->middleware('throttle:5,1')->name('magic_send');
    Route::get('magic-verify', [AuthClienteController::class, 'verifyMagicLink'])->name('magic_verify');
    Route::post('logout', [AuthClienteController::class, 'logout'])->name('logout');

    Route::get('perfil', [PerfilClienteController::class, 'showPerfil'])
        ->middleware(AuthCliente::class)
        ->name('perfil');
});

// Encuestas Públicas de Experiencia (F7-07)
Route::get('encuesta/{token}', [EncuestaPublicaController::class, 'mostrar'])->name('encuesta.responder');
Route::post('encuesta/{token}', [EncuestaPublicaController::class, 'guardar'])->middleware('throttle:10,1')->name('encuesta.guardar');

require __DIR__.'/auth.php';
