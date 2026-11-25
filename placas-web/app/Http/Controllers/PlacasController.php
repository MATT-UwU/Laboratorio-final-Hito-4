<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlacasController extends Controller
{
    public function index(): View
    {
        return view('placas', ['entornos' => config('placas')]);
    }

    public function procesar(Request $request): View
    {
        $entornos = config('placas');

        $datos = $request->validate([
            'entorno' => ['required', Rule::in(array_keys($entornos))],
            'imagen' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $entorno = $entornos[$datos['entorno']];
        $imagen = $request->file('imagen');
        $vista = ['entornos' => $entornos, 'elegido' => $datos['entorno']];

        if (! is_file((string) $entorno['python'])) {
            return view('placas', $vista + ['error' => 'No se encontró el Python de '.$datos['entorno'].'. Revisa el archivo .env.']);
        }

        // Laravel ejecuta el Python del entorno elegido.
        $comando = [$entorno['python'], $entorno['script'], $imagen->getRealPath()];
        // getenv() pasa todas las variables del sistema (SystemRoot, USERPROFILE...).
        // Sin esto, desde el servidor web Python arranca casi sin variables y falla.
        $proceso = Process::env(getenv())->timeout(120)->run($comando);

        if ($proceso->failed()) {
            return view('placas', $vista + ['error' => trim($proceso->errorOutput() ?: $proceso->output())]);
        }

        $lineas = preg_split('/\R/', trim($proceso->output()));

        return view('placas', $vista + [
            'resultado' => json_decode(end($lineas), true, flags: JSON_THROW_ON_ERROR),
            'comando' => implode(' ', [$entorno['python'], $entorno['script'], $imagen->getClientOriginalName()]),
        ]);
    }
}
