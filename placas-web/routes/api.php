<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

use App\Http\Controllers\AgenteController;
use App\Http\Controllers\AlertasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasosController;
use App\Http\Controllers\PoseController;
use App\Http\Controllers\VehiculosController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth — pública
    Route::post('/auth/google', [AuthController::class, 'apiGoogle']);

    // Dispositivo de alerta (ESP32): protegido con su propio token X-Device-Token
    Route::get('/dispositivo/estado', [\App\Http\Controllers\DispositivoController::class, 'estado'])
        ->middleware('throttle:120,1');

    // Protegidas con token
    Route::middleware('api.token')->group(function () {

        // Pasos
        Route::post('/pasos/frame',                   [PasosController::class, 'recibirFrame']);
        Route::get('/placas/{placa}/historial',       [PasosController::class, 'historial']);
        Route::get('/placas-filtradas',               [\App\Http\Controllers\PlacasFiltradasController::class, 'apiIndex']);

        // Consulta de placas
        Route::post('/placas/consultar',              [AgenteController::class, 'consultar']);
        Route::post('/placas/consultar-imagen',       [AgenteController::class, 'consultarImagen']);

        // Agente chat
        Route::post('/agente/chat',                   [AgenteController::class, 'chat']);

        // Análisis de pose humana (servicio Colab independiente del cobro de peaje)
        Route::post('/pose/analizar',                 [PoseController::class, 'analizar']);

        // Casetas
        Route::get('/casetas', fn () => \App\Models\Caseta::select('id', 'nombre', 'ubicacion')->orderBy('id')->get());
        Route::get('/casetas/{id}/resumen',           [AlertasController::class, 'resumenCaseta']);

        // Vehículos
        Route::apiResource('vehiculos', VehiculosController::class)->names('api.vehiculos');
    });
});
