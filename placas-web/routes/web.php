<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

use App\Http\Controllers\AgenteController;
use App\Http\Controllers\AlertasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasosController;
use App\Http\Controllers\VehiculosController;
use Illuminate\Support\Facades\Route;

// ─── Públicas ────────────────────────────────────────────────────────────────
Route::get('/login',               [AuthController::class, 'showLogin'])->name('login');
Route::get('/login/demo',          [AuthController::class, 'demoLogin'])->name('login.demo');
Route::get('/auth/google',         [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback',[AuthController::class, 'handleGoogleCallback']);

// ─── Autenticadas ────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // SSE — tiempo real
    Route::get('/pasos/stream', [PasosController::class, 'stream'])->name('pasos.stream');

    // Pasos (historial web)
    Route::get('/pasos', [PasosController::class, 'index'])->name('pasos.index');

    // Procesar fotos subidas desde la web (mismo flujo que la caseta)
    Route::get('/procesar', [PasosController::class, 'procesarForm'])->name('procesar.form');
    Route::post('/procesar', [PasosController::class, 'procesarWeb'])->name('procesar.enviar');

    // Placas que cumplen el filtro del estudiante (números 2…2, letras con M)
    Route::get('/placas-filtradas', [\App\Http\Controllers\PlacasFiltradasController::class, 'index'])->name('placas-filtradas.index');

    // Vehículos (CRUD — solo admin)
    Route::middleware('role:admin')->group(function () {
        Route::resource('vehiculos', VehiculosController::class);
    });

    // Alertas
    Route::get('/alertas', [AlertasController::class, 'index'])->name('alertas.index');
    Route::post('/alertas/{alerta}/atender', [AlertasController::class, 'atender'])->name('alertas.atender');

    // Chat (agente web)
    Route::get('/chat', fn() => view('chat'))->name('chat');
    Route::post('/chat', [AgenteController::class, 'chat'])->name('chat.send');
    Route::post('/chat/imagen', [AgenteController::class, 'consultarImagen'])->name('chat.image');

    // Estado del endpoint de inferencia
    Route::get('/estado', fn() => view('estado'))->name('estado');
});
