{{-- © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4 --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reconocimiento de placas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4" style="max-width: 960px">
    <h1 class="h3">Reconocimiento de placas de vehículos</h1>
    <p class="text-secondary">Un detector YOLO localiza la placa y el lector del entorno elegido lee sus caracteres.</p>
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link active" href="{{ route('placas.index') }}">Una imagen</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('inferencia.index') }}">Inferencia por lotes (3 entornos)</a></li>
    </ul>

    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('placas.procesar') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="entorno" class="form-label">Entorno y lector</label>
                    <select id="entorno" name="entorno" class="form-select @error('entorno') is-invalid @enderror">
                        @foreach ($entornos as $clave => $entorno)
                            <option value="{{ $clave }}" @selected(old('entorno', $elegido ?? 'venv') === $clave)>{{ $entorno['nombre'] }}</option>
                        @endforeach
                    </select>
                    @error('entorno')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <label for="imagen" class="form-label">Foto del vehículo (JPG, PNG o WebP, hasta 10 MB)</label>
                <div class="input-group">
                    <input id="imagen" name="imagen" type="file" accept=".jpg,.jpeg,.png,.webp"
                           class="form-control @error('imagen') is-invalid @enderror" required>
                    <button class="btn btn-primary" type="submit">Reconocer</button>
                    @error('imagen')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </form>
        </div>
    </div>

    @isset($error)
        <div class="alert alert-danger" style="white-space: pre-wrap">{{ $error }}</div>
    @endisset

    @isset($resultado)
        <img src="{{ $resultado['imagen'] }}" alt="Imagen con las placas detectadas" class="img-fluid rounded border mb-3">

        <h2 class="h5">{{ $resultado['lector'] }}</h2>
        @if (count($resultado['placas']) === 0)
            <div class="alert alert-warning">No se detectó ninguna placa.</div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered bg-white">
                    <thead>
                    <tr><th>Placa leída</th><th>Formato boliviano</th><th>Confianza de detección</th><th>Confianza de lectura</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($resultado['placas'] as $placa)
                        <tr>
                            <td class="fs-5"><code>{{ $placa['texto'] ?: '(sin lectura)' }}</code></td>
                            <td>{{ $placa['formato_bolivia'] ? 'Sí' : 'No' }}</td>
                            <td>{{ $placa['confianza_deteccion'] }} %</td>
                            <td>{{ $placa['confianza_lectura'] === null ? 'No disponible' : $placa['confianza_lectura'].' %' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="card mt-3">
            <div class="card-header">Conexión con el entorno</div>
            <div class="card-body small">
                <p class="mb-1"><strong>Comando:</strong> <code>{{ $comando }}</code></p>
                <p class="mb-1"><strong>Python usado:</strong> <code>{{ $resultado['ejecutable'] }}</code></p>
                <p class="mb-1"><strong>Versiones:</strong> Python {{ $resultado['python'] }}, {{ $resultado['libreria'] }}</p>
                <p class="mb-0"><strong>Tiempo:</strong> {{ $resultado['milisegundos'] }} ms</p>
            </div>
        </div>
    @endisset
</main>
</body>
</html>