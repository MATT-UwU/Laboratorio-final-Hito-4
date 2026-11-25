<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inferencia por lotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .miniatura { width: 190px; height: 124px; object-fit: cover; border-radius: 4px; }
        .tabla td, .tabla th { vertical-align: middle; }
        .placa { font-family: SFMono-Regular, Consolas, monospace; font-size: 1.02rem; font-weight: 600; }
        .vacio { color: #9aa3ab; font-style: italic; }
    </style>
</head>
<body class="bg-light">
<main class="container-fluid py-4" style="max-width: 1280px">
    <h1 class="h3">Reconocimiento de placas de vehículos</h1>
    <p class="text-secondary">Un detector YOLO localiza la placa y el lector de cada entorno lee sus caracteres.</p>
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link" href="{{ route('placas.index') }}">Una imagen</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('inferencia.index') }}">Inferencia por lotes (3 entornos)</a></li>
    </ul>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <form method="POST" action="{{ route('inferencia.procesar') }}">
                @csrf
                <button class="btn btn-primary" type="submit">Procesar las {{ count($imagenes) }} imágenes con los 3 entornos</button>
            </form>
            <span class="text-secondary small">Carpeta <code>INFERENCIA</code>. Los tres entornos se ejecutan en paralelo.
                @if ($datos) Última ejecución: {{ $datos['fecha'] }} ({{ $datos['segundos'] }} s). @endif</span>
        </div>
    </div>

    @if (! $datos)
        <div class="alert alert-info">Todavía no hay resultados. Pulsa el botón para procesar las imágenes.</div>
    @else
        @foreach ($datos['resumen'] as $clave => $r)
            @if ($r['error'])
                <div class="alert alert-danger" style="white-space: pre-wrap">{{ $entornos[$clave]['nombre'] }}: {{ $r['error'] }}</div>
            @endif
        @endforeach

        <div class="row g-3 mb-3">
            @foreach ($datos['resumen'] as $clave => $r)
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-secondary small">{{ $entornos[$clave]['nombre'] }}</div>
                            <div class="fs-3 fw-semibold">{{ $r['aciertos'] }} / {{ count($imagenes) }} aciertos</div>
                            <div class="small text-secondary">{{ $r['libreria'] }} · Python {{ $r['python'] }} · {{ number_format($r['milisegundos'] / 1000, 1) }} s</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="table-responsive">
            <table class="table table-bordered bg-white tabla">
                <thead class="table-light">
                <tr>
                    <th>N.º</th>
                    <th>Imagen (detección)</th>
                    <th>Placa real</th>
                    @foreach ($entornos as $entorno)
                        <th>{{ $entorno['nombre'] }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($imagenes as $i => $nombre)
                    @php($fila = $datos['filas'][$nombre] ?? [])
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            @isset($fila['imagen'])
                                <img class="miniatura" src="{{ $fila['imagen'] }}" alt="{{ $nombre }}">
                            @endisset
                            <div class="small text-secondary">{{ $nombre }}</div>
                        </td>
                        <td class="placa">{{ config('inferencia.placas_reales')[$nombre] ?? '?' }}</td>
                        @foreach ($entornos as $clave => $entorno)
                            @php($celda = $fila[$clave] ?? null)
                            <td class="{{ $celda && $celda['acierto'] ? 'table-success' : 'table-danger' }}">
                                @if (! $celda)
                                    <span class="vacio">sin resultado</span>
                                @elseif (count($celda['placas']) === 0)
                                    <span class="vacio">no detectó placa</span>
                                @else
                                    @foreach ($celda['placas'] as $p)
                                        <div>
                                            <span class="placa">{{ $p['texto'] ?: '—' }}</span>
                                            <span class="small text-secondary">{{ $p['confianza_lectura'] === null ? '' : $p['confianza_lectura'].' %' }}</span>
                                        </div>
                                    @endforeach
                                @endif
                                <div class="small fw-semibold">{{ $celda && $celda['acierto'] ? '✔ Acierto' : '✘ Fallo' }}</div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th colspan="3">Total de aciertos</th>
                    @foreach ($datos['resumen'] as $r)
                        <th>{{ $r['aciertos'] }} / {{ count($imagenes) }}</th>
                    @endforeach
                </tr>
                </tfoot>
            </table>
        </div>
        <p class="small text-secondary">Acierto: alguna de las placas leídas coincide exactamente con la placa real (solo letras y números).
            Las imágenes no son de Bolivia, así que ninguna cumple el formato boliviano.</p>
    @endif
</main>
</body>
</html>
