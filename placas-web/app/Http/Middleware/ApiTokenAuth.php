<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Si ya está autenticado por sesión web (ej. desde el navegador)
        if (Auth::check()) {
            return $next($request);
        }

        // 2. Extraer Bearer token del header
        $authHeader = $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);

            // Tokens de prueba/desarrollo
            if (config('services.demo_login') && ($token === 'demo_token_operador' || $token === 'demo_token')) {
                $user = User::where('rol', 'operador')->first() ?? User::first();
                if ($user) {
                    $request->setUserResolver(fn() => $user);
                    return $next($request);
                }
            }

            // Validar en caché
            $userId = cache()->get("api_token_{$token}");
            if ($userId) {
                $user = User::find($userId);
                if ($user && $user->activo) {
                    $request->setUserResolver(fn() => $user);
                    return $next($request);
                }
            }
        }

        return response()->json(['error' => 'No autorizado. Se requiere token Bearer válido.'], 401);
    }
}
