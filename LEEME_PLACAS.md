# Reconocimiento de placas: app Flutter + API Python (conda)

Instrucciones para dejar funcionando este proyecto en una PC nueva (Windows).
Autor del laboratorio: Matias Dario Mercier Rengel (fila 12 de la tabla).

> **Aviso de alcance:** esta guía describe el detector anterior independiente (Flask, puerto 5000 y CSV). No es el backend del Sistema de Peaje actual. La app `IAM_yolo_native_app-main` ahora apunta a Laravel por el puerto 8000; Laravel reenvía las imágenes a `INFERENCE_URL` (Colab/RunPod). Para la integración actual, seguir la sección 5 de [RESUMEN_SESION_HITO_PEAJE.md](./RESUMEN_SESION_HITO_PEAJE.md).

## Qué es

- `placas-web/`: detector YOLOv9 (ONNX) + lector Tesseract, expuestos como API HTTP (Flask).
  El archivo clave es `placas-web/python/placas/servidor_api.py`.
- `IAM_yolo_native_app-main/`: app Flutter. Manda cada frame de la cámara a `POST /predict`
  y dibuja la caja y el texto de la placa. Verde = cumple la regla, naranja = no.
- `placas-app.apk`: la app ya compilada (instalar en el teléfono sin recompilar).
- `placas-venv/` y `placas-venv.rar`: NO sirven en otra PC (un venv no es portable). Ignorarlos.

La app NO lee placas por sí sola: necesita la API corriendo en la PC.

## Regla de registro

Una placa se registra si **empieza por 2, termina en 2 y contiene la letra M** (ej. `2M12`, `27M2`).
Se guarda sin duplicados en `placas-web/storage/app/placas_registradas.csv`.
Se cambia con variables de entorno `REGLA_EMPIEZA`, `REGLA_TERMINA`, `REGLA_CONTIENE`.

## 1. Encender la API (lo mínimo para usar el APK)

1. Instalar Anaconda o Miniconda.
2. Crear el entorno (solo conda-forge; así no hay que aceptar los Términos de Servicio de Anaconda):
   ```
   conda create -y -n placas-conda --override-channels -c conda-forge python=3.11 onnxruntime tesseract pytesseract numpy flask pip
   <python del entorno> -m pip install opencv-python-headless
   ```
   Usar el OpenCV de **pip**, no el de conda: Windows (Control de aplicaciones) puede bloquear el de conda-forge.
3. Verificar:
   ```
   <python del entorno> -c "import cv2,numpy,onnxruntime,pytesseract,flask;print('ok')"
   ```
4. Arrancar: doble clic en `placas-web/iniciar_api_placas.bat`, o
   ```
   cd placas-web
   <python del entorno> python\placas\servidor_api.py
   ```
   Si conda no está en `%LOCALAPPDATA%\anaconda3`, editar la ruta dentro del `.bat`.
5. Comprobar: abrir `http://127.0.0.1:5000/` debe mostrar `{"estado":"ok",...}`.

Modelos necesarios (ya incluidos en `placas-web/python/placas/modelo/`):
`yolo-v9-t-384-license-plates-end2end.onnx`. Tesseract usa `eng.traineddata` que trae el paquete conda.

## 2. Conectar el teléfono

Tipo de conexión usada: **USB con `adb reverse`** (el APK apunta a `http://127.0.0.1:5000`).
La app antes usaba la IP de la red, pero el teléfono y la PC estaban en redes distintas.

1. Activar "Depuración USB" en el teléfono y conectarlo.
2. `adb devices` debe listarlo (adb: `<Android SDK>\platform-tools\adb.exe`).
3. `adb reverse tcp:5000 tcp:5000` (repetir cada vez que se reconecta el cable).
4. Instalar: `adb install -r placas-app.apk` (o copiar el APK al teléfono).
5. Abrir la app con la pantalla encendida y dar permiso de cámara.

Alternativa sin cable: teléfono y PC en la misma red; poner la IP de la PC en `apiUrl` de
`IAM_yolo_native_app-main/lib/main.dart` y recompilar el APK.
La API escucha en `0.0.0.0:5000`.

## 3. Recompilar el APK (solo si se cambia la app)

```
cd IAM_yolo_native_app-main
flutter build apk --release
```
Salida: `build/app/outputs/flutter-apk/app-release.apk`.

Requisitos y puntos que ya se resolvieron (no repetir el error):
- Editar `android/local.properties`: `sdk.dir` y `flutter.sdk` deben apuntar a las rutas de ESTA PC.
- Kotlin se subió a `2.2.20` en `android/settings.gradle.kts` (con 2.1.0 Flutter 3.47 no compila).
- `AndroidManifest.xml` ya tiene el permiso `INTERNET` y `usesCleartextTraffic="true"` (la API es http).
- La app está bloqueada en horizontal (`sensorLandscape`), por eso no se rota el frame.
- El APK se firma con la clave debug: sirve para instalar, no para Play Store.
- Si `flutter doctor` pide licencias de Android SDK, las debe aceptar el usuario
  (`flutter doctor --android-licenses`); no aceptarlas en su nombre.

## 4. Laravel (opcional)

La interfaz web de `placas-web` no es necesaria para la app. Si se usa, en `placas-web/.env`
`PY_PLACAS_CONDA` debe apuntar al `python.exe` del entorno `placas-conda` de ESTA PC
(entre comillas simples) y luego `php artisan optimize:clear`. Las líneas de venv y mamba
tienen rutas del usuario original y no funcionan.

## 5. Problemas conocidos

| Síntoma | Causa | Solución |
|---|---|---|
| App en rojo "Desconectado" | La app no llega a la API | API encendida y `adb reverse tcp:5000 tcp:5000` ejecutado |
| `Error opening data file ... eng.traineddata` | Tesseract busca en la carpeta equivocada | Ya corregido en `placas_tesseract.py` (elige la carpeta que contiene `eng.traineddata`) |
| `CAMERA_DISABLED ... from background` | La cámara se abrió con la app en segundo plano | La app reintenta sola; mantener la pantalla encendida |
| `import cv2` falla por "Control de aplicaciones" | OpenCV de conda bloqueado por Windows | Usar `opencv-python-headless` de pip. No desactivar Smart App Control |
| Lee mal o no lee la placa | Tesseract es un OCR general | Acercar la placa, quedarse quieto, buena luz. Mejora posible: `fast-plate-ocr` |

## 6. Prueba rápida sin teléfono

```
curl -F "file=@placas-web/INFERENCIA/Norway_1007317.jpg" http://127.0.0.1:5000/predict
```
Debe devolver la placa `KH90284` (no cumple la regla, así que no se registra).
