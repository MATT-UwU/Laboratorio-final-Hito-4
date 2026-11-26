<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Usar paginación simple compatible con Tailwind
        Paginator::useBootstrapFive();
    }
}
