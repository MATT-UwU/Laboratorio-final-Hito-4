# Continuar inferencia de placas en otra sesion

## Objetivo

Compilar y ejecutar el proyecto Laravel usando todos los directorios disponibles:

- Proyecto: `C:\laragon\www\Nueva carpeta\placas-web`
- Entorno virtual disponible: `C:\laragon\www\Nueva carpeta\placas-venv`
- Dataset solicitado: `C:\laragon\www\Nueva carpeta\placas-web\img\DATASEET`
- Scripts: `C:\laragon\www\Nueva carpeta\placas-web\python\placas\`

**Estado al 24/11/2026:** completada la inferencia de las 19 imagenes de `img\DATASEET`. Los resultados resumidos estan guardados en `storage\app\inferencia_dataset.csv` y `storage\app\inferencia_dataset.json`. La siguiente etapa es levantar el servidor unificado en Google Colab para conectar la inferencia de la pagina Laravel.

## Diagnostico conocido

El entorno `placas-venv` existe en esta copia, pero no funciona en esta PC porque su Python base apunta a una ruta de la cuenta anterior.

Su archivo `pyvenv.cfg` apunta a:

```text
C:\Users\Matias\AppData\Local\Programs\Python\Python311\python.exe
```

La ruta no existe en esta sesion. No recrear ni borrar el entorno sin permiso. La inferencia local se ejecuto correctamente con el Python de Laragon:

```powershell
C:\laragon\bin\python\python-3.13\python.exe
```

Las dependencias necesarias ya cargaron en ese Python (`cv2`, `fast_plate_ocr` y `onnxruntime`); no se instalaron paquetes durante esta sesion. Los resultados confirmados contienen 19 filas CSV y 19 resultados JSON; el JSON resumido no incluye imagenes base64. Las lecturas OCR aun no se validaron manualmente contra etiquetas de referencia.

## Restricciones

- No modificar la logica de los scripts Python salvo que sea imprescindible.
- No desactivar Smart App Control, WDAC ni ninguna configuracion de seguridad.
- No borrar directorios completos ni reinstalar Windows.
- No cambiar secretos ni credenciales.
- No registrar secretos nuevos en el repositorio.
- Usa siempre comillas dobles o simples alrededor de rutas con espacios.
- Ejecuta las comprobaciones y muestra los resultados.

## 1. Entrar al proyecto

```powershell
Set-Location "C:\laragon\www\Nueva carpeta\placas-web"
```

## 2. Localizar todos los Python disponibles

```powershell
Get-ChildItem "C:\laragon" -Filter python.exe -Recurse -File -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty FullName

Get-ChildItem "C:\Users" -Filter python.exe -Recurse -File -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty FullName
```

## 3. Estado del entorno Python

El venv se probo y fallo porque intenta iniciar el Python anterior:

```powershell
& "C:\laragon\www\Nueva carpeta\placas-venv\Scripts\python.exe" -c "import cv2, fast_plate_ocr, onnxruntime; print('ok')"
```

Para usar Fast-Plate-OCR localmente, usa el Python de Laragon que ya se verifico:

```powershell
& "C:\laragon\bin\python\python-3.13\python.exe" -c "import cv2, fast_plate_ocr, onnxruntime; print('ok', cv2.__version__)"
```

No reinstalar dependencias ni modificar `pyvenv.cfg` para repetir la inferencia ya completada.

## 4. Ejecutar la inferencia Fast-Plate-OCR

```powershell
$imagenes = Get-ChildItem "C:\laragon\www\Nueva carpeta\placas-web\img\DATASEET" -File |
    Where-Object { $_.Extension -match "\.(jpg|jpeg|png|webp)$" } |
    Sort-Object Name |
    ForEach-Object { $_.FullName }

& "C:\laragon\bin\python\python-3.13\python.exe" `
    "python\placas\placas_fastocr.py" $imagenes
```

La ejecucion para las 19 imagenes ya se completo. Repite este comando solo si se quiere regenerar la salida.

## 5. Guardar un resumen sin las imagenes base64

```powershell
$temporales = @(& "C:\laragon\bin\python\python-3.13\python.exe" `
    "python\placas\placas_fastocr.py" $imagenes)

$resultados = foreach ($linea in $temporales) {
    if ($linea.StartsWith("{")) {
        $r = $linea | ConvertFrom-Json
        [PSCustomObject]@{
            archivo = $r.archivo
            lector = $r.lector
            placas = ($r.placas | ForEach-Object { $_.texto }) -join " / "
            confianza_deteccion = ($r.placas | ForEach-Object { $_.confianza_deteccion }) -join " / "
            confianza_lectura = ($r.placas | ForEach-Object { $_.confianza_lectura }) -join " / "
            milisegundos = $r.milisegundos
        }
    }
}

$resultados |
    Export-Csv "storage\app\inferencia_dataset.csv" `
        -NoTypeInformation -Encoding UTF8

$obj = [PSCustomObject]@{
    fecha = (Get-Date -Format "s")
    carpeta = "img/DATASEET"
    cantidad = $resultados.Count
    resultados = $resultados
}

$obj |
    ConvertTo-Json -Depth 8 |
    Set-Content "storage\app\inferencia_dataset.json" -Encoding UTF8
```

## 6. Comprobar la salida

La comprobacion ya se realizo: ambos archivos existen, el CSV tiene 19 filas y el JSON tiene 19 resultados sin imagenes base64.

```powershell
Import-Csv "storage\app\inferencia_dataset.csv" |
    Format-Table -AutoSize

Test-Path "storage\app\inferencia_dataset.json"
Test-Path "storage\app\inferencia_dataset.csv"
```

## 7. Siguiente etapa: API de inferencia en Colab

El proyecto incluye el notebook `C:\laragon\www\Nueva carpeta\Notebooks\servidor_inferencia_peaje_colab.ipynb`. Este notebook levanta `inferencia\servidor_unificado.py` como API temporal en Colab con GPU; no es necesario entrenar modelos ni ejecutar otros notebooks para la primera prueba.

1. En Google Colab, abrir `Notebooks/servidor_inferencia_peaje_colab.ipynb` y seleccionar un runtime GPU.
2. Ejecutar las celdas en orden. Cuando se solicite, seleccionar por separado estos archivos locales:
   - `C:\laragon\www\Nueva carpeta\inferencia\servidor_unificado.py`
   - `C:\laragon\www\Nueva carpeta\placas-web\python\placas\modelo\yolo-v9-t-384-license-plates-end2end.onnx`
3. Opcionalmente subir `m2_best.pt` (modelo de segmentacion propio) y `llantas_best.pt` (modelo M3 de keypoints de llantas). Si se omiten, M2 usa el modelo COCO `yolo11n-seg.pt` y los ejes se estiman por categoria.
4. Introducir el token de forma oculta y mantenerlo privado. El notebook inicia la API y el tunel Cloudflare y muestra una URL temporal.
5. Configurar en `placas-web/.env` `INFERENCE_URL` con la URL base (sin `/predict`) y `INFERENCE_API_TOKEN` con el mismo token. Despues ejecutar `php artisan optimize:clear`.
6. Mantener el runtime Colab activo durante la prueba. Si se reinicia, volver a ejecutar el notebook y actualizar `INFERENCE_URL`, porque la URL temporal puede cambiar.

El notebook paso validacion local de JSON y sintaxis Python, pero aun **no se ejecuto en un runtime real de Colab**. Falta validar GPU, carga de modelos, URL publica y solicitud desde Laravel.

## 8. Configurar Laravel para la inferencia en Colab

Para el flujo actual Laravel reenvia las imagenes al servidor Python de Colab; no necesita ejecutar `placas-venv` localmente. En `placas-web/.env`, configura las variables existentes con la URL base de Cloudflare (sin `/predict`) y el mismo token introducido en Colab:

```dotenv
INFERENCE_URL=https://tu-url-temporal.trycloudflare.com
INFERENCE_API_TOKEN=el_mismo_secreto_privado_de_colab
```

No publiques ni compartas el token o el contenido de `.env`. Despues de cambiar la configuracion:

```powershell
Set-Location "C:\laragon\www\Nueva carpeta\placas-web"
php artisan optimize:clear
```

Las variables `PY_PLACAS_VENV`, `PY_PLACAS_CONDA` y `PY_PLACAS_MAMBA` corresponden a integraciones locales/anteriores; no son necesarias para el endpoint unificado de Colab.

## 9. Estado y entrega

Al terminar, informa:

1. Inferencia local: completada, 19 de 19 imagenes. Los resultados son predicciones OCR pendientes de validacion manual.
2. Python usado: `C:\laragon\bin\python\python-3.13\python.exe`. No se instalaron dependencias ni se reparo el venv.
3. Inferencia en Colab: notebook preparado, ejecucion real pendiente.
4. Archivos generados:
   - `storage/app/inferencia_dataset.json`
   - `storage/app/inferencia_dataset.csv`
