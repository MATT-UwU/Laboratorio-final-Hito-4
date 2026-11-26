<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Redirige al flujo oficial de Google OAuth 2.0 (sin Socialite)
     */
    public function redirectToGoogle()
    {
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect', '/auth/google/callback');

        if (empty($clientId) || empty(config('services.google.client_secret'))) {
            return redirect('/login')->with('error', 'Credenciales GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET no configuradas en el archivo .env');
        }

        // Si la URI es relativa, convertir a absoluta
        if (!str_starts_with($redirectUri, 'http')) {
            $redirectUri = url($redirectUri);
        }

        $params = http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid profile email',
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);

        return redirect("https://accounts.google.com/o/oauth2/v2/auth?" . $params);
    }

    /**
     * Procesa el callback de Google OAuth 2.0
     */
    public function handleGoogleCallback(Request $request)
    {
        if ($request->has('error')) {
            return redirect('/login')->with('error', 'Acceso cancelado por el usuario en Google.');
        }

        $code = $request->query('code');
        if (empty($code)) {
            return redirect('/login')->with('error', 'Código de autorización de Google no recibido.');
        }

        $clientId     = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri  = config('services.google.redirect', '/auth/google/callback');
        if (!str_starts_with($redirectUri, 'http')) {
            $redirectUri = url($redirectUri);
        }

        try {
            // 1. Canjear el código por tokens
            $tokenRes = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code'          => $code,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
            ]);

            if (!$tokenRes->successful()) {
                return redirect('/login')->with('error', 'Error al verificar con Google: ' . $tokenRes->body());
            }

            $tokenData   = $tokenRes->json();
            $accessToken = $tokenData['access_token'] ?? null;

            // 2. Obtener datos del usuario de Google
            $userRes = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
            if (!$userRes->successful()) {
                return redirect('/login')->with('error', 'No se pudo obtener información del perfil de Google.');
            }

            $googleData = $userRes->json();
            $email      = $googleData['email'] ?? null;
            $googleId   = $googleData['sub'] ?? null;
            $name       = $googleData['name'] ?? 'Usuario Google';
            $avatar     = $googleData['picture'] ?? null;

            // 3. Validar lista blanca en la base de datos (Sección 4.4 de los requisitos)
            $user = User::where('email', $email)->first();

            if (!$user) {
                return redirect('/login')->with('error', "Acceso denegado: El correo {$email} no está en la lista blanca de usuarios autorizados.");
            }

            if (!$user->activo) {
                return redirect('/login')->with('error', 'Tu cuenta está desactivada. Contacta al administrador.');
            }

            // Actualizar datos del usuario
            $user->update([
                'google_id' => $googleId,
                'name'      => $name,
                'avatar'    => $avatar,
            ]);

            Auth::login($user, true);

            return redirect()->intended('/dashboard');

        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Error de conexión con Google: ' . $e->getMessage());
        }
    }

    /**
     * Acceso rápido de demostración para el docente o administrador
     */
    public function demoLogin(Request $request)
    {
        abort_unless(config('services.demo_login'), 404);
        $rol = $request->query('rol', 'admin');
        
        if ($rol === 'admin') {
            $user = User::where('email', 'doc.alvaro.pinto.ma@unifranz.edu.bo')->first();
            if (!$user) {
                $user = User::create([
                    'name'   => 'Álvaro Pinto (Docente)',
                    'email'  => 'doc.alvaro.pinto.ma@unifranz.edu.bo',
                    'rol'    => 'admin',
                    'activo' => true,
                ]);
            }
        } else {
            $user = User::where('rol', 'operador')->first();
            if (!$user) {
                $user = User::create([
                    'name'   => 'Operador Caseta',
                    'email'  => 'operador@peaje.com',
                    'rol'    => 'operador',
                    'activo' => true,
                ]);
            }
        }

        Auth::login($user, true);
        return redirect()->intended('/dashboard');
    }

    /** Logout */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    /** Página de login */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }
        return view('auth.login');
    }

    // ─── API: recibe token de Google desde la app móvil ───────────────────────
    public function apiGoogle(Request $request)
    {
        $request->validate(['token' => 'required|string']);
        $token = $request->token;

        $email = null;
        $name = null;
        $googleId = null;
        $avatar = null;

        // Si es el token de demo de la app
        if (config('services.demo_login') && ($token === 'demo_token' || $token === 'demo_token_operador')) {
            $user = User::firstOrCreate(
                ['email' => 'operador@peaje.com'],
                ['name' => 'Operador Caseta', 'rol' => 'operador', 'activo' => true]
            );
        } else {
            // Validar token con Google OAuth tokeninfo oficial
            try {
                $res = Http::get("https://oauth2.googleapis.com/tokeninfo?id_token={$token}");
                if (!$res->successful()) {
                    // Intentar como access_token
                    $res = Http::withToken($token)->get('https://www.googleapis.com/oauth2/v3/userinfo');
                }

                if (!$res->successful()) {
                    return response()->json(['error' => 'Token de Google inválido'], 401);
                }

                $data = $res->json();
                $email = $data['email'] ?? null;
                $googleId = $data['sub'] ?? null;
                $name = $data['name'] ?? 'Usuario Google';
                $avatar = $data['picture'] ?? null;

                $user = User::where('email', $email)->first();
                if (!$user || !$user->activo) {
                    return response()->json(['error' => 'Acceso denegado: cuenta no autorizada'], 403);
                }

                $user->update([
                    'google_id' => $googleId,
                    'name'      => $name,
                    'avatar'    => $avatar,
                ]);

            } catch (\Exception $e) {
                return response()->json(['error' => 'Error validando con Google: ' . $e->getMessage()], 500);
            }
        }

        // Generar un token Bearer seguro para la API
        $apiToken = base64_encode($user->id . '|' . Str::random(40));

        // Guardar token en sesión o caché para validación
        cache()->put("api_token_{$apiToken}", $user->id, now()->addDays(30));

        return response()->json([
            'token' => $apiToken,
            'user'  => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'rol'    => $user->rol,
                'avatar' => $user->avatar,
            ],
        ]);
    }
}
