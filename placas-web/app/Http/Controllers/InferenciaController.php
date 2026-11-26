<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class InferenciaController extends Controller
{
    /** Muestra la tabla con el último resultado guardado. */
    public function index(): View
    {
        $archivo = config('inferencia.resultados');
        $datos = is_file($archivo) ? json_decode(file_get_contents($archivo), true) : null;

        return view('inferencia', [
            'entornos' => config('placas'),
            'imagenes' => $this->imagenes(),
            'datos' => $datos,
        ]);
    }

    /** Procesa todas las imágenes con los tres entornos a la vez y guarda el resultado. */
    public function procesar(): RedirectResponse
    {
        set_time_limit(0);
        $entornos = config('placas');
        $imagenes = $this->imagenes();
        $rutas = array_map(fn ($n) => config('inferencia.carpeta').DIRECTORY_SEPARATOR.$n, $imagenes);

        $inicio = microtime(true);
        // Cada entorno recibe las 20 imágenes en un solo proceso (carga su modelo una vez)
        // y los tres procesos corren en paralelo.
        $procesos = Process::pool(function (Pool $pool) use ($entornos, $rutas) {
            foreach ($entornos as $clave => $entorno) {
                $pool->as($clave)->env(getenv())->timeout(900)
                    ->command(array_merge([$entorno['python'], $entorno['script']], $rutas));
            }
        })->start()->wait();

        $reales = config('inferencia.placas_reales');
        $filas = [];
        $resumen = [];
        foreach ($entornos as $clave => $entorno) {
            $proceso = $procesos[$clave];
            $resumen[$clave] = ['aciertos' => 0, 'milisegundos' => 0, 'error' => null, 'libreria' => null, 'python' => null];
            if ($proceso->failed()) {
                $resumen[$clave]['error'] = trim($proceso->errorOutput() ?: $proceso->output());
            }
            foreach (preg_split('/\R/', trim($proceso->output())) as $linea) {
                if (! str_starts_with($linea, '{')) {
                    continue;
                }
                $r = json_decode($linea, true);
                $real = $reales[$r['archivo']] ?? null;
                $textos = array_column($r['placas'], 'texto');
                $acierto = $real !== null && in_array($real, $textos, true);

                $filas[$r['archivo']][$clave] = [
                    'placas' => $r['placas'],
                    'acierto' => $acierto,
                    'milisegundos' => $r['milisegundos'],
                ];
                // La imagen anotada se guarda una sola vez (la del primer entorno).
                $filas[$r['archivo']]['imagen'] ??= $this->miniatura($r['imagen'], $r['archivo']);
                $resumen[$clave]['aciertos'] += $acierto ? 1 : 0;
                $resumen[$clave]['milisegundos'] += $r['milisegundos'];
                $resumen[$clave]['libreria'] = $r['libreria'];
                $resumen[$clave]['python'] = $r['python'];
            }
        }

        file_put_contents(config('inferencia.resultados'), json_encode([
            'fecha' => now()->format('d/m/Y H:i'),
            'segundos' => round(microtime(true) - $inicio, 1),
            'filas' => $filas,
            'resumen' => $resumen,
        ]));

        return redirect()->route('inferencia.index');
    }

    /**
     * Guarda la imagen anotada reducida a 400 px en public/miniaturas y devuelve su URL.
     * Así la página no lleva varios MB de imágenes en base64.
     */
    private function miniatura(string $dataUri, string $archivo): ?string
    {
        $original = imagecreatefromstring(base64_decode(substr($dataUri, strpos($dataUri, ',') + 1)));
        if ($original === false) {
            return null;
        }
        // No puede llamarse "inferencia": chocaría con la ruta /inferencia.
        $carpeta = public_path('miniaturas');
        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }
        $nombre = pathinfo($archivo, PATHINFO_FILENAME).'.jpg';
        imagejpeg(imagescale($original, 400), $carpeta.DIRECTORY_SEPARATOR.$nombre, 82);

        return asset('miniaturas/'.$nombre).'?v='.time();
    }

    /** Nombres de las imágenes de la carpeta INFERENCIA (sin las capturas INFERENCIA_N.png). */
    private function imagenes(): array
    {
        $nombres = array_map('basename', glob(config('inferencia.carpeta').DIRECTORY_SEPARATOR.'*.{jpg,jpeg,JPG,JPEG,webp}', GLOB_BRACE));
        sort($nombres, SORT_NATURAL | SORT_FLAG_CASE);

        return $nombres;
    }
}
