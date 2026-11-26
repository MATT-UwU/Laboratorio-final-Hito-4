<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AgenteController extends Controller
{
    /**
     * POST /api/v1/agente/chat  — recibe una placa en texto
     * POST /api/v1/placas/consultar
     */
    public function consultar(Request $request)
    {
        $request->validate(['placa' => 'required|string|max:30']);
        $placa = Vehiculo::normalizar($request->placa);
        return response()->json($this->estadoPlaca($placa));
    }

    /**
     * POST /api/v1/placas/consultar-imagen — recibe imagen, llama a M1 + OCR
     */
    public function consultarImagen(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:jpeg,jpg,png,webp']);

        $inferenceUrl = config('services.inference.url', '');
        if (empty($inferenceUrl)) {
            return response()->json(['error' => 'INFERENCE_URL no configurado'], 503);
        }
        $inferenceToken = config('services.inference.token', '');
        if (empty($inferenceToken)) {
            return response()->json(['error' => 'INFERENCE_API_TOKEN no configurado'], 503);
        }

        try {
            $file = $request->file('file');
            $resp = Http::withHeaders(['X-API-Token' => $inferenceToken])
                ->connectTimeout(5)
                ->timeout(30)
                ->attach('file', file_get_contents($file->getRealPath()), 'placa.jpg')
                ->post(rtrim($inferenceUrl, '/') . '/predict');
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Error al contactar inferencia'], 503);
        }

        if (!$resp->successful()) {
            logger()->warning('El endpoint de inferencia rechazó la consulta por imagen.', [
                'status' => $resp->status(),
            ]);
            return response()->json(['error' => 'El endpoint de inferencia falló'], 502);
        }

        $data = $resp->json();
        if (!is_array($data) || !array_key_exists('placa', $data) || (!is_null($data['placa']) && !is_string($data['placa']))) {
            logger()->warning('El endpoint de inferencia devolvió una respuesta incompatible.');
            return response()->json(['error' => 'La respuesta de inferencia tiene un formato inválido'], 502);
        }

        $placa = isset($data['placa'])
            ? (Vehiculo::extraerPlacaBoliviana($data['placa']) ?? Vehiculo::normalizar($data['placa']))
            : null;

        if (!$placa) {
            return response()->json([
                'estado'   => 'sin_placa',
                'mensaje'  => 'No se detectó ninguna placa en la imagen. Intenta con otra foto con mejor iluminación.',
            ]);
        }

        // Filtro de placas del estudiante: también se guarda si llega por consulta con foto.
        $placaFiltrada = \App\Models\PlacaFiltrada::registrarSiCumple($placa, [
            'confianza_ocr' => is_numeric($data['confianza'] ?? null) ? $data['confianza'] : null,
            'origen'        => 'consulta',
            'usuario_id'    => $request->user()?->id,
        ]);

        return response()->json(array_merge(
            [
                'placa_detectada' => $placa,
                'confianza'       => $data['confianza'] ?? null,
                'cumple_filtro'   => $placaFiltrada !== null,
            ],
            $this->estadoPlaca($placa)
        ));
    }

    /**
     * POST /api/v1/agente/chat — para el chat web (texto libre)
     */
    public function chat(Request $request)
    {
        $request->validate(['mensaje' => 'required|string|max:500']);
        $mensaje = $request->mensaje;

        // 1) Agente con LLM (Qwen en RunPod) y function calling. Si no está disponible, se usan reglas.
        if (config('services.llm.habilitado')) {
            $respuestaLlm = $this->chatConLlm($mensaje);
            if ($respuestaLlm !== null) {
                return response()->json($respuestaLlm);
            }
        }

        // Aceptar códigos alfanuméricos y evitar confundir palabras como "dime" con una placa.
        preg_match_all('/(?<![A-Z0-9])[A-Z0-9]{4,8}(?![A-Z0-9])/i', $mensaje, $matches);
        $placas = array_values(array_unique(array_filter(
            array_map(fn ($valor) => Vehiculo::normalizar($valor), $matches[0]),
            fn ($valor) => preg_match('/[A-Z]/', $valor) && preg_match('/[0-9]/', $valor)
        )));

        if (count($placas) !== 1) {
            return response()->json([
                'respuesta' => count($placas) > 1
                    ? 'Por favor consulta una placa a la vez.'
                    : 'Por favor escribe la placa que deseas consultar (ej: 2M12, ABC123).',
            ]);
        }

        $posiblePlaca = $placas[0];
        $estado = $this->estadoPlaca($posiblePlaca);

        return response()->json([
            'placa'     => $posiblePlaca,
            'respuesta' => $estado['mensaje'],
            'datos'     => $estado,
            'motor'     => 'reglas',
        ]);
    }

    /**
     * Agente con LLM propio (Qwen vía Ollama en RunPod) usando function calling.
     * El LLM solo decide qué herramienta llamar y redacta la respuesta; los datos salen
     * exclusivamente de la base de datos a través de las herramientas. Devuelve null si falla.
     */
    private function chatConLlm(string $mensaje): ?array
    {
        // El LLM corre en RunPod (LLM_URL); si no se configuró, se usa el mismo servidor de inferencia.
        $url = rtrim((string) (config('services.llm.url') ?: config('services.inference.url', '')), '/');
        $token = (string) (config('services.llm.token') ?: config('services.inference.token', ''));
        if ($url === '' || $token === '') {
            return null;
        }

        $herramientas = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'consultar_placa',
                    'description' => 'Consulta en la base de datos del peaje el estado de una placa: si está registrada, '
                        . 'si tiene pasos (fecha, caseta, categoría, monto), si fue detectada sin registro o si no existe. '
                        . 'Normaliza la placa y sugiere coincidencias por errores de OCR (O/0, I/1, B/8, S/5).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['placa' => ['type' => 'string', 'description' => 'Placa tal como la escribió el usuario']],
                        'required' => ['placa'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'historial_placa',
                    'description' => 'Devuelve los últimos pasos registrados de una placa (fecha, caseta, categoría, ejes, pasajeros, monto, estado).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['placa' => ['type' => 'string']],
                        'required' => ['placa'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'datos_vehiculo',
                    'description' => 'Datos del vehículo registrado con esa placa: propietario, categoría, ejes, saldo en Bs y si está activo.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['placa' => ['type' => 'string']],
                        'required' => ['placa'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'resumen_del_dia',
                    'description' => 'Resumen de HOY: cantidad de pasos, recaudación en Bs (pasos cobrados), pasos por estado y por caseta. '
                        . 'Opcionalmente de una sola caseta (por nombre o id, p. ej. "Norte").',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['caseta' => ['type' => 'string', 'description' => 'Nombre o id de la caseta; vacío = todas']],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'alertas',
                    'description' => 'Alertas del sistema (placa no registrada / sin saldo): cuántas hay pendientes y las últimas 10 con placa, caseta y fecha.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'solo_pendientes' => ['type' => 'boolean', 'description' => 'true = solo sin atender (por defecto)'],
                            'solo_hoy' => ['type' => 'boolean', 'description' => 'true = solo las de hoy'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'placas_filtradas',
                    'description' => 'Placas detectadas que cumplen el filtro del estudiante (números empiezan y terminan en 2, letras contienen M).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['solo_hoy' => ['type' => 'boolean']],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'trafico',
                    'description' => 'Tráfico por día: cantidad de vehículos, camiones, vehículos por categoría y pasajeros contados. '
                        . 'Por defecto hoy; con "dias" devuelve los últimos N días (máx. 30) hasta "fecha".',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'fecha' => ['type' => 'string', 'description' => 'Último día del rango en formato AAAA-MM-DD; vacío = hoy'],
                            'dias' => ['type' => 'integer', 'description' => 'Cantidad de días hacia atrás (1 = solo ese día)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'tarifas',
                    'description' => 'Tarifas vigentes en Bs por categoría (moto, auto, minibus, camion, bus) y número de ejes.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'categoria' => ['type' => 'string', 'enum' => ['moto', 'auto', 'minibus', 'camion', 'bus']],
                            'ejes' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ];

        $mensajes = [
            ['role' => 'system', 'content' => 'Eres el agente del sistema de control de peaje. Respondes en español, breve y claro. '
                . 'Para cualquier pregunta sobre una placa DEBES llamar a una herramienta y responder SOLO con los datos que devuelve; '
                . 'nunca inventes fechas, montos ni propietarios. Usa el estado devuelto: registrada_con_paso, registrada_sin_paso, '
                . 'identificada_no_registrada (posible evasión) o no_existe. Si hay sugerencias, pregunta "¿Quisiste decir…?". '
                . 'También puedes informar datos del vehículo, el resumen del día (pasos y recaudación por caseta), el tráfico por día '
                . '(vehículos, camiones y pasajeros), alertas, '
                . 'placas filtradas y tarifas, siempre usando las herramientas. Montos en Bs. '
                . 'Si el usuario pregunta por una placa sin darla, pídesela. No respondas temas ajenos al peaje.'],
            ['role' => 'user', 'content' => $mensaje],
        ];

        $ultimoEstado = null;
        try {
            for ($ronda = 0; $ronda < 4; $ronda++) {
                $resp = Http::withHeaders(['X-API-Token' => $token])
                    ->connectTimeout(5)->timeout(60)
                    ->post($url . '/llm/chat', ['messages' => $mensajes, 'tools' => $herramientas]);
                if (!$resp->successful()) {
                    return null;
                }
                $msg = $resp->json('message') ?? [];
                $llamadas = $msg['tool_calls'] ?? [];
                $mensajes[] = ['role' => 'assistant', 'content' => $msg['content'] ?? '', 'tool_calls' => $llamadas];

                if (empty($llamadas)) {
                    $texto = trim((string) ($msg['content'] ?? ''));
                    if ($texto === '') {
                        return null;
                    }
                    return [
                        'placa'       => $ultimoEstado['placa'] ?? null,
                        'respuesta'   => $texto,
                        'datos'       => $ultimoEstado,
                        'motor'       => 'llm',
                        'modelo'      => $resp->json('model'),
                        'latencia_ms' => $resp->json('latencia_ms'),
                    ];
                }

                foreach ($llamadas as $llamada) {
                    $nombre = $llamada['function']['name'] ?? '';
                    $args = $llamada['function']['arguments'] ?? [];
                    if (is_string($args)) {
                        $args = json_decode($args, true) ?: [];
                    }
                    $resultado = $this->ejecutarHerramienta($nombre, is_array($args) ? $args : [], $ultimoEstado);

                    $mensajes[] = ['role' => 'tool', 'content' => json_encode($resultado, JSON_UNESCAPED_UNICODE)];
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * Ejecuta una herramienta pedida por el LLM. Todas son de SOLO LECTURA sobre la base de datos.
     * $ultimoEstado se actualiza cuando se consulta el estado de una placa (para devolver 'datos').
     */
    private function ejecutarHerramienta(string $nombre, array $args, ?array &$ultimoEstado): array
    {
        $placaDe = function () use ($args): string {
            $texto = (string) ($args['placa'] ?? '');
            return Vehiculo::extraerPlacaBoliviana($texto) ?? Vehiculo::normalizar($texto);
        };
        $casetaDe = function () use ($args) {
            $texto = trim((string) ($args['caseta'] ?? ''));
            if ($texto === '') {
                return null;
            }
            return \App\Models\Caseta::where('nombre', 'like', '%' . $texto . '%')
                ->orWhere('id', is_numeric($texto) ? (int) $texto : 0)->first();
        };

        switch ($nombre) {
            case 'consultar_placa':
                $placa = $placaDe();
                if ($placa === '') {
                    return ['error' => 'Placa vacía o inválida'];
                }
                $ultimoEstado = $this->estadoPlaca($placa);
                return $this->resumenParaLlm($ultimoEstado);

            case 'historial_placa':
                $placa = $placaDe();
                if ($placa === '') {
                    return ['error' => 'Placa vacía o inválida'];
                }
                return [
                    'placa' => $placa,
                    'pasos' => \App\Models\Paso::with('caseta')->where('placa_normalizada', $placa)
                        ->latest()->limit(5)->get()
                        ->map(fn ($p) => [
                            'fecha' => $p->created_at->format('d/m/Y H:i'), 'caseta' => $p->caseta?->nombre,
                            'categoria' => $p->categoria_detectada, 'ejes' => $p->ejes_detectados,
                            'pasajeros' => $p->pasajeros, 'monto_bs' => $p->monto, 'estado' => $p->estado,
                        ])->all(),
                ];

            case 'datos_vehiculo':
                $placa = $placaDe();
                $v = $placa !== '' ? Vehiculo::where('placa', $placa)->first() : null;
                if (!$v) {
                    return ['placa' => $placa, 'registrado' => false, 'sugerencias' => $placa !== '' ? $this->sugerencias($placa) : []];
                }
                return [
                    'placa' => $v->placa, 'registrado' => true, 'propietario' => $v->propietario,
                    'categoria' => $v->categoria, 'ejes' => $v->ejes, 'saldo_bs' => $v->saldo, 'activo' => (bool) $v->activo,
                ];

            case 'resumen_del_dia':
                $caseta = $casetaDe();
                $q = \App\Models\Paso::whereDate('created_at', today());
                if ($caseta) {
                    $q->where('caseta_id', $caseta->id);
                }
                $porCaseta = \App\Models\Paso::with('caseta')->whereDate('created_at', today())
                    ->selectRaw("caseta_id, count(*) as pasos, sum(case when estado = 'ok' then monto else 0 end) as recaudado, sum(monto) as calculado")
                    ->groupBy('caseta_id')->get()
                    ->map(fn ($r) => ['caseta' => $r->caseta?->nombre, 'pasos' => (int) $r->pasos, 'recaudado_bs' => round((float) $r->recaudado, 2), 'monto_calculado_bs' => round((float) $r->calculado, 2)])
                    ->all();
                return [
                    'fecha' => today()->format('d/m/Y'),
                    'caseta' => $caseta?->nombre ?? 'todas',
                    'pasos' => (clone $q)->count(),
                    'recaudado_bs' => round((float) (clone $q)->where('estado', 'ok')->sum('monto'), 2),
                    'monto_total_calculado_bs' => round((float) (clone $q)->sum('monto'), 2),
                    'por_estado' => (clone $q)->selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado'),
                    'por_caseta' => $caseta ? null : $porCaseta,
                ];

            case 'alertas':
                $soloPendientes = ($args['solo_pendientes'] ?? true) !== false;
                $q = \App\Models\Alerta::with('paso.caseta')->latest();
                if ($soloPendientes) {
                    $q->where('atendida', false);
                }
                if (($args['solo_hoy'] ?? false) === true) {
                    $q->whereDate('created_at', today());
                }
                return [
                    'pendientes_total' => \App\Models\Alerta::where('atendida', false)->count(),
                    'alertas' => $q->limit(10)->get()->map(fn ($a) => [
                        'fecha' => $a->created_at->format('d/m/Y H:i'), 'tipo' => $a->tipo, 'atendida' => (bool) $a->atendida,
                        'placa' => $a->paso?->placa_normalizada, 'caseta' => $a->paso?->caseta?->nombre,
                    ])->all(),
                ];

            case 'placas_filtradas':
                $q = \App\Models\PlacaFiltrada::with('caseta')->latest();
                if (($args['solo_hoy'] ?? false) === true) {
                    $q->whereDate('created_at', today());
                }
                return [
                    'regla' => \App\Support\FiltroPlaca::descripcion(),
                    'total' => (clone $q)->count(),
                    'placas_distintas' => (clone $q)->distinct('placa')->count('placa'),
                    'ultimas' => $q->limit(10)->get()->map(fn ($p) => [
                        'placa' => $p->placa, 'fecha' => $p->created_at->format('d/m/Y H:i'),
                        'origen' => $p->origen, 'caseta' => $p->caseta?->nombre,
                    ])->all(),
                ];

            case 'trafico':
                // Tráfico por día: vehículos por categoría (incluye camiones) y pasajeros contados.
                $dias = max(1, min(30, (int) ($args['dias'] ?? 1)));
                $hasta = !empty($args['fecha']) ? \Carbon\Carbon::parse((string) $args['fecha'])->startOfDay() : today();
                $desde = (clone $hasta)->subDays($dias - 1);
                $filas = \App\Models\Paso::whereBetween('created_at', [$desde, (clone $hasta)->endOfDay()])
                    ->selectRaw('DATE(created_at) as dia, categoria_detectada as categoria, count(*) as pasos, '
                        . 'sum(coalesce(pasajeros, 0)) as pasajeros, sum(case when pasajeros is null then 0 else 1 end) as con_conteo')
                    ->groupBy('dia', 'categoria')->orderBy('dia')->get();
                $porDia = [];
                foreach ($filas as $f) {
                    $d = (string) $f->dia;
                    $porDia[$d] ??= ['fecha' => \Carbon\Carbon::parse($d)->format('d/m/Y'), 'vehiculos' => 0, 'camiones' => 0,
                        'pasajeros' => 0, 'pasos_con_conteo_de_pasajeros' => 0, 'por_categoria' => []];
                    $porDia[$d]['vehiculos'] += (int) $f->pasos;
                    $porDia[$d]['pasajeros'] += (int) $f->pasajeros;
                    $porDia[$d]['pasos_con_conteo_de_pasajeros'] += (int) $f->con_conteo;
                    $porDia[$d]['por_categoria'][$f->categoria ?? 'sin_categoria'] = (int) $f->pasos;
                    if ($f->categoria === 'camion') {
                        $porDia[$d]['camiones'] += (int) $f->pasos;
                    }
                }
                return [
                    'desde' => $desde->format('d/m/Y'),
                    'hasta' => $hasta->format('d/m/Y'),
                    'dias' => array_values($porDia),
                    'nota' => 'Cada paso es un vehículo registrado por una caseta; pasajeros = personas visibles contadas por el modelo de segmentación.',
                ];

            case 'tarifas':
                $q = \App\Models\Tarifa::where('activa', true)->orderBy('categoria')->orderBy('ejes');
                if (!empty($args['categoria'])) {
                    $q->where('categoria', strtolower((string) $args['categoria']));
                }
                if (!empty($args['ejes'])) {
                    $q->where('ejes', (int) $args['ejes']);
                }
                return ['tarifas' => $q->get(['categoria', 'ejes', 'monto'])->map(fn ($t) => [
                    'categoria' => $t->categoria, 'ejes' => $t->ejes, 'monto_bs' => $t->monto,
                ])->all()];
        }

        return ['error' => "Herramienta desconocida: {$nombre}"];
    }

    /** Versión compacta del estado de una placa para enviar al LLM (sin modelos Eloquent completos). */
    private function resumenParaLlm(array $estado): array
    {
        $ultimo = $estado['ultimo_paso'] ?? null;
        $vehiculo = $estado['vehiculo'] ?? null;

        return array_filter([
            'placa'       => $estado['placa'] ?? null,
            'estado'      => $estado['estado'] ?? null,
            'mensaje'     => $estado['mensaje'] ?? null,
            'sugerencias' => $estado['sugerencias'] ?? null,
            'propietario' => $vehiculo?->propietario,
            'categoria_registrada' => $vehiculo?->categoria,
            'saldo_bs'    => $vehiculo?->saldo,
            'ultimo_paso' => $ultimo ? [
                'fecha' => $ultimo->created_at->format('d/m/Y H:i'),
                'caseta' => $ultimo->caseta?->nombre,
                'categoria' => $ultimo->categoria_detectada,
                'monto_bs' => $ultimo->monto,
            ] : null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function estadoPlaca(string $placa): array
    {
        $vehiculo = Vehiculo::where('placa', $placa)->first();

        // Sugerir coincidencias con errores típicos de OCR (O↔0, I↔1, B↔8, S↔5)
        $sugerencias = [];
        if (!$vehiculo) {
            $sugerencias = $this->sugerencias($placa);
        }

        $pasos = \App\Models\Paso::where('placa_normalizada', $placa)
            ->orderByDesc('created_at')
            ->limit(5)
            ->with('caseta')
            ->get();

        // Determinar estado
        if ($vehiculo && $pasos->isNotEmpty()) {
            $ultimo = $pasos->first();
            return [
                'estado'      => 'registrada_con_paso',
                'placa'       => $placa,
                'vehiculo'    => $vehiculo,
                'ultimo_paso' => $ultimo,
                'mensaje'     => "✅ Placa {$placa} registrada. Último paso: {$ultimo->created_at->format('d/m/Y H:i')} · Caseta: {$ultimo->caseta?->nombre} · Categoría: {$ultimo->categoria_detectada} · Monto: Bs {$ultimo->monto}.",
                'historial'   => $pasos,
            ];
        }

        if ($vehiculo && $pasos->isEmpty()) {
            return [
                'estado'   => 'registrada_sin_paso',
                'placa'    => $placa,
                'vehiculo' => $vehiculo,
                'mensaje'  => "📋 Placa {$placa} está registrada en el sistema pero nunca ha pasado por el peaje.",
            ];
        }

        // Buscar si la cámara la detectó alguna vez (paso sin vehículo registrado)
        $detectado = $pasos->isNotEmpty();

        if ($detectado) {
            return [
                'estado'      => 'identificada_no_registrada',
                'placa'       => $placa,
                'sugerencias' => $sugerencias,
                'mensaje'     => "⚠️ Placa {$placa} fue detectada por la cámara pero NO está registrada en el sistema. Posible evasión." . ($sugerencias ? " ¿Quisiste decir: " . implode(', ', $sugerencias) . "?" : ""),
            ];
        }

        return [
            'estado'      => 'no_existe',
            'placa'       => $placa,
            'sugerencias' => $sugerencias,
            'mensaje'     => "❌ No existe ningún registro de la placa {$placa}." . ($sugerencias ? " ¿Quisiste decir: " . implode(', ', $sugerencias) . "?" : ""),
        ];
    }

    /** Genera variaciones de la placa por errores típicos de OCR */
    private function sugerencias(string $placa): array
    {
        $mapa = ['O' => '0', '0' => 'O', 'I' => '1', '1' => 'I', 'B' => '8', '8' => 'B', 'S' => '5', '5' => 'S'];
        $variantes = [];

        for ($i = 0; $i < strlen($placa); $i++) {
            $c = $placa[$i];
            if (isset($mapa[$c])) {
                $variante = substr($placa, 0, $i) . $mapa[$c] . substr($placa, $i + 1);
                if (Vehiculo::where('placa', $variante)->exists()) {
                    $variantes[] = $variante;
                }
            }
        }
        return array_values(array_unique($variantes));
    }
}
