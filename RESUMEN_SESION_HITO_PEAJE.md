# 📋 Informe de Avance y Resumen de Sesión — Sistema de Control de Peaje Inteligente
> **Fecha de sesión:** 24/11/2026  
> **Proyecto:** Laboratorio Final de Hito — Sistema Inteligente de Control de Peaje  
> **Autor:** Matias Dario Mercier Rengel  

---

## 🎯 1. Resumen Ejecutivo de la Sesión

Al inicio de la sesión, el proyecto contaba únicamente con el laboratorio anterior: un detector de placas local en Python con YOLOv9 + Tesseract y una app móvil Flutter básica sin autenticación ni módulos de peaje (avance real: **~10–15%**).

Durante las sesiones se implementó la estructura principal del sistema. La integración con modelos reales, ejecución de los notebooks, credenciales de producción y pruebas en teléfono/VPS requieren la configuración descrita al final de este documento; no se deben considerar desplegados hasta completar esas pruebas.

---

## 🏗️ 2. Componentes Desarrollados e Implementados

### 2.1 Base de Datos y Modelos Relacionales (Laravel / Eloquent)
Se definieron y migraron las 6 tablas del esquema mínimo obligatorio:
1. **`users`**: Soporte para autenticación Google OAuth 2.0 (`google_id`, `avatar`, `rol`: `admin`/`operador`, `activo`).
2. **`casetas`**: Gestión de puntos de control (`nombre`, `ubicacion`, `activa`).
3. **`vehiculos`**: Registro del parque automotor (`placa`, `propietario`, `categoria`, `ejes`, `saldo`, `activo`).
4. **`tarifas`**: Matriz de cobro según categoría del vehículo y número de ejes.
5. **`pasos`**: Registro de tránsitos (`placa_leida`, `placa_normalizada`, `confianza_ocr`, `categoria_detectada`, `ejes_detectados`, `monto`, `caseta_id`, `usuario_id`, `imagen_url`, `estado`, `inferencia_raw`).
6. **`alertas`**: Registro de incidencias (`paso_id`, `tipo`: `no_registrado`, `sin_saldo`, `evasion`, `atendida`).

**Seeders precargados (`PeajeSeeder.php`):**
- **20 vehículos de prueba** con distintas categorías, ejes y saldos.
- **3 casetas** (Norte, Sur, Este).
- **8 tarifas oficiales** según categoría y ejes.
- **Usuario administrador docente**: `doc.alvaro.pinto.ma@unifranz.edu.bo` precargado con rol `admin`.

---

### 2.2 Panel Web y Tiempo Real (Server-Sent Events)
Diseñado con Blade y Tailwind CSS (interfaz moderna en modo oscuro):
* **Dashboard en tiempo real (`/dashboard`)**:
  * Métricas en vivo: pasos acumulados del día, recaudación en Bs, alertas sin atender y vehículos activos.
  * Tabla dinámica de tránsitos recientes que se actualiza al instante sin recargar la página vía **SSE** (`/pasos/stream`).
  * Monitor de alertas activas y resumen por caseta.
* **Historial de Pasos (`/pasos`)**: Registro histórico con visor de evidencias fotográficas.
* **CRUD de Vehículos (`/vehiculos`)**: Búsqueda, creación, edición y eliminación de vehículos con permisos de Administrador.
* **Módulo de Alertas (`/alertas`)**: Listado de alertas pendientes con acción de atención inmediata.
* **Estado de la Inferencia (`/estado`)**: Monitor de estado y latencia del endpoint de IA en la nube.

---

### 2.3 Autenticación Google OAuth 2.0 Nativa y Lista Blanca
* Implementada con el cliente HTTP nativo de Laravel (sin dependencias frágiles de composer):
  * **Redirección segura**: Flujo oficial de Google Identity.
  * **Validación de lista blanca**: Solo las cuentas de Google registradas en la tabla `users` pueden acceder; las demás son denegadas.
  * **Accesos de prueba rápidos**: Botones para ingresar como `Admin (Docente)` u `Operador Caseta` mientras se vincula la app en Google Cloud Console.

---

### 2.4 Agente Conversacional (`AgenteController.php` y `/chat`)
* Interfaz de chat web y endpoint REST `/api/v1/agente/chat`.
* Clasificación en los **4 estados requeridos**:
  1. `Registrada y con paso`: Informa fecha, caseta, categoría y monto del último paso.
  2. `Registrada sin paso`: Indica que el vehículo existe pero no ha circulado.
  3. `Identificada no registrada`: Detectada por cámara pero sin registro previo (posible evasión).
  4. `No existe`: Ningún registro en el sistema.
* **Normalización y sugerencia inteligente**: Algoritmo que detecta confusiones típicas de OCR (`O↔0`, `I↔1`, `B↔8`, `S↔5`) y sugiere alternativas (*"¿Quisiste decir...?"*).
* **Consulta por imagen (`/api/v1/placas/consultar-imagen`)**: Envío de fotografías para extracción de placa y respuesta del agente.

---

### 2.5 Endpoint Unificado de Inferencia Nube (`/inferencia`)
Creado en la carpeta `inferencia/` para ejecución en **RunPod** o **Google Colab**:
* `servidor_unificado.py`: Servidor FastAPI que integra:
  * **M1**: Detección de placas (YOLOv9 ONNX) + OCR (EasyOCR / Tesseract).
  * **M2**: Segmentación de instancias (YOLO-seg) → Categoría del vehículo.
  * **M3**: Estimación de ejes a partir de keypoints de ruedas (YOLO-pose).
  * Devuelve el JSON unificado exigido: `{ placa, confianza, categoria, ejes, latencia_ms }`.
* `Dockerfile` y `requirements.txt`: Listo para desplegar en contenedor en RunPod (+5 puntos de bonificación).
* `diagnostico.py`: Script para registrar versiones de Python, PyTorch, CUDA y Ultralytics (+5 puntos de bonificación).

---

### 2.6 Aplicación Móvil Flutter (`IAM_yolo_native_app-main`)
Actualizado `lib/main.dart`:
* **Pantalla de Login**: Inicio de sesión con Google Sign-In (o credencial de operador demo).
* **Modo Caseta**:
  * Transmisión continua de video a la API (`POST /api/v1/pasos/frame`).
  * Indicador de color según estado (Verde = OK, Rojo = No registrado / Alerta, Naranja = Sin saldo).
  * Visualización de categoría detectada, número de ejes y tarifa calculada.
* **Modo Consulta**:
  * Consulta por texto manual.
  * Consulta por foto tomada desde la cámara o galería al agente conversacional.

---

### 2.7 Documentación de la API
* Creada la colección Postman v2.1 oficial en:  
  `docs/api_peaje_postman_collection.json` con todos los endpoints de autenticación, casetas, pasos, placas y agente.

---

## 📁 3. Estructura Actual de Archivos Clave

```
Nueva carpeta/
├── docs/
│   └── api_peaje_postman_collection.json     # Colección Postman completa
├── IAM_yolo_native_app-main/                 # App Móvil Flutter
│   ├── lib/main.dart                         # Modos Caseta, Consulta y Google Auth
│   └── pubspec.yaml
├── inferencia/                               # Endpoint de los 3 Modelos para RunPod/Colab
│   ├── servidor_unificado.py                 # FastAPI: M1 + M2 + M3
│   ├── requirements.txt                      # Dependencias fijadas
│   ├── Dockerfile                            # Despliegue en contenedor RunPod
│   └── diagnostico.py                        # Script de diagnóstico para informe (+5 pts)
├── Notebooks/                                # Notebooks de entrenamiento
├── placas-web/                               # Aplicación Web Laravel (Subdominio)
│   ├── app/Http/Controllers/
│   │   ├── AuthController.php                # Google OAuth 2.0 nativo y lista blanca
│   │   ├── DashboardController.php           # Métricas en vivo
│   │   ├── PasosController.php               # Tráfico de frames, cobro y SSE
│   │   ├── VehiculosController.php           # CRUD de vehículos
│   │   ├── AlertasController.php             # Gestión de alertas
│   │   └── AgenteController.php              # Agente conversacional y 4 estados
│   ├── app/Models/                           # User, Vehiculo, Caseta, Tarifa, Paso, Alerta
│   ├── database/migrations/                  # Migraciones de las 6 tablas
│   ├── database/seeders/                     # Seeder con 20 vehículos y cuenta docente
│   ├── resources/views/                      # Vistas Blade con Tailwind CSS
│   ├── routes/web.php                        # Rutas web del panel
│   ├── routes/api.php                        # API REST v1
│   ├── .env                                  # Configuración de entorno local (SQLite)
│   └── .env.example                          # Plantilla para producción (MySQL/VPS)
└── RESUMEN_SESION_HITO_PEAJE.md              # Documentación de la sesión
```

---

## 🚀 4. Siguientes Pasos (versión anterior; ver sección 5)

Los pasos operativos actuales para Colab, Laravel y Flutter están en la sección 5. La guía externa citada abajo corresponde al plan anterior y no fue validada en este proyecto.

1. **Subir al VPS de Hostinger**:
   * Seguir los pasos de la [guia_despliegue_vps_runpod.md](file:///C:/Users/renge/.gemini/antigravity/brain/b8fc9e8f-5f2e-42b3-ba1d-f93b31c33531/guia_despliegue_vps_runpod.md) para clonar el repositorio, configurar Nginx, SSL con Certbot y MySQL.
2. **Obtener Credenciales de Google OAuth**:
   * Crear el proyecto en Google Cloud Console y colocar el `Client ID` y `Client Secret` en el `.env` del VPS.
3. **Levantar el Endpoint de Inferencia en RunPod**:
   * Ejecutar `servidor_unificado.py` en un Pod con GPU y colocar la URL pública en `INFERENCE_URL` de Laravel.
4. **Grabar Video de Demostración** (8 a 12 minutos) mostrando el flujo de pantalla dividida entre la app móvil y el panel web.

---

## 🧭 5. Continuación: prueba integral Laravel + Colab + Flutter

### Arquitectura correcta

```text
App Flutter ──HTTP + Bearer──> Laravel (API, BD, reglas y cobro)
                                     │
                                     └──HTTPS + X-API-Token──> API Python en Colab/RunPod

Chat web ──> Laravel ──imagen──> API Python
Consulta por texto ──> Laravel/BD (no necesita llamar a Python)
```

La app **no debe apuntar directamente a Colab**. Flutter apunta a Laravel; Laravel envía los frames/fotos al endpoint de inferencia. `INFERENCE_URL` es la URL base pública del servidor Python, sin `/predict` al final.

### Qué notebooks usar

| Archivo | Uso para este proyecto | Importante |
|---|---|---|
| `Notebooks/servidor_inferencia_peaje_colab.ipynb` | Levantar y probar la API unificada de inferencia | Usa GPU de Colab, carga M1 desde ONNX y M2 COCO por defecto; M3 de llantas es opcional. Publica una URL temporal protegida por token. |
| `Notebooks/keypoints_llantas_yolo_detectron2_v3.ipynb` | Entrenar M3 (keypoints de llantas) | Requiere imágenes de vehículos y etiquetas revisadas. El YOLO-pose queda en `/content/runs/pose/llantas/weights/best.pt`. Copiarlo a Google Drive antes de cerrar Colab. |
| `Notebooks/week_3_segmentacion_vehiculos_yolo_detectron2_simplificado.ipynb` | Probar M2 (segmentación/categoría) | Es un notebook de experimentación, no el servidor HTTP. El espacio de trabajo no incluye un `best.pt` propio; para la primera prueba el servidor puede usar `yolo11n-seg.pt` preentrenado, con las limitaciones de clases COCO. |
| `Notebooks/week_2_servidor_pose_colab_v2.ipynb` | Servicio opcional de pose humana | Detecta personas y 17 keypoints COCO. Se consume por una ruta Laravel separada; no detecta vehículos, ejes ni quién conduce. |
| `placas-web/python/placas/modelo/yolo-v9-t-384-license-plates-end2end.onnx` | Modelo M1 para detectar la placa | Ya está en el proyecto; no hay que entrenarlo. El notebook de servicio lo sube directamente al runtime temporal de Colab. |

No ejecutar todos los notebooks como servicios separados. Laravel espera un único contrato `{ placa, confianza, categoria, ejes, latencia_ms }`; `inferencia/servidor_unificado.py` es el adaptador que combina M1, M2 y M3. Si no se configura el peso entrenado M3, se informa una estimación básica por categoría, no una inferencia real de llantas.

La excepción es el notebook de pose humana: se ejecuta como servicio Colab **independiente y opcional** para probar keypoints de personas. No reemplaza ni modifica el contrato de peaje.

### A. Preparar el servidor de inferencia en Colab

1. Abrir `Notebooks/servidor_inferencia_peaje_colab.ipynb` en Google Colab y seleccionar un runtime GPU. El notebook verifica CUDA y que ONNX Runtime tenga `CUDAExecutionProvider`.
2. Ejecutar las celdas en orden. El notebook abre diálogos separados para subir `inferencia/servidor_unificado.py` y `placas-web/python/placas/modelo/yolo-v9-t-384-license-plates-end2end.onnx`. Después pregunta si se desea subir `m2_best.pt` y `llantas_best.pt`; se pueden omitir para la prueba inicial. Si se omite M2, usa `yolo11n-seg.pt` de COCO; si se omite M3, los ejes son una estimación por categoría.
3. Introducir el token de forma oculta cuando el notebook lo solicite. Configurar el mismo valor localmente en `INFERENCE_API_TOKEN`; no guardar el secreto en una celda ni compartirlo.
4. El notebook inicia la API, comprueba los modelos y crea un túnel Cloudflare temporal. Copiar el valor `INFERENCE_URL` que imprime y mantener Colab conectado. Al reiniciar el runtime, se debe actualizar la URL en Laravel.
5. En `placas-web/.env`, configurar `INFERENCE_URL` y `INFERENCE_API_TOKEN` como se describe en el apartado B y ejecutar `php artisan optimize:clear`.

### B. Configurar y probar Laravel

En `placas-web/.env`, configurar con la URL obtenida y **el mismo secreto** introducido en Colab:

```dotenv
INFERENCE_URL=https://....trycloudflare.com
INFERENCE_API_TOKEN=el_mismo_secreto_privado
```

En PowerShell, desde `placas-web`:

```powershell
php artisan migrate --seed
php artisan optimize:clear
php artisan serve --host=0.0.0.0 --port=8000
```

Si la base ya está migrada, basta con `php artisan db:seed --class=PeajeSeeder` y `php artisan optimize:clear`. Abrir `http://127.0.0.1:8000/login/demo?rol=operador`, luego `/chat`. Probar texto (`2M12`) y subir una foto. Para el modo Caseta, observar `/dashboard` o `/pasos`.

Prueba directa opcional del notebook desde Windows, sustituyendo el token solo en la consola privada:

```powershell
curl.exe -F "file=@C:\laragon\www\Nueva carpeta\placas-web\INFERENCIA\Bahrain_1003919.jpg" `
  -H "X-API-Token: TU_TOKEN" https://....trycloudflare.com/predict
```

La respuesta debe incluir `placa`, `confianza`, `categoria`, `ejes` y `latencia_ms`. Un HTTP 401 significa que el token no coincide; 503 normalmente indica que falta configuración o que Colab ya se desconectó.

### B.1. Levantar el servicio independiente de pose humana

1. Abrir `Notebooks/week_2_servidor_pose_colab_v2.ipynb` en Colab, elegir un runtime con GPU y ejecutar las celdas en orden. El notebook instala Detectron2 y Cloudflare Tunnel, carga Keypoint R-CNN preentrenado y arranca FastAPI en el puerto 8000.
2. En la celda de configuración se genera `API_TOKEN`. Copiar ese valor de forma privada: se regenera al reiniciar el runtime y **no** es el token de `INFERENCE_API_TOKEN`.
3. La última celda de servidor levanta `/predict` y el túnel. Copiar el valor `URL_PUBLICA` (`https://....trycloudflare.com`) y mantener el runtime y la celda ejecutándose. `/health` debe devolver `estado: ok`; el notebook también ejecuta una predicción de prueba.
4. En `placas-web/.env`, agregar la URL base (sin `/predict`) y el token recién copiado:

```dotenv
POSE_INFERENCE_URL=https://....trycloudflare.com
POSE_INFERENCE_API_TOKEN=token_generado_por_el_notebook
```

Ejecutar `php artisan optimize:clear` después de cambiar `.env`. Al reiniciar Colab, actualizar ambos valores. No colocar este token en Flutter ni publicarlo.

El notebook recibe en `POST /predict` los bytes de la imagen original y el header `X-API-Token`; no recibe un formulario multipart. Laravel adapta la petición desde el multipart que envía Flutter. La ruta de la aplicación es `POST /api/v1/pose/analizar` (autenticada con Bearer), con el archivo en el campo `file`. Laravel valida la respuesta y devuelve la caja, las 17 articulaciones COCO nombradas y las partes con confianza `>= 0.5`. Este endpoint no crea pasos, alertas ni cobros.

En Flutter, iniciar sesión como Operador y usar **Analizar pose de personas** para tomar una foto. La prueba acordada usa una cámara frontal hacia el parabrisas y, por ahora, solo cuenta personas y muestra sus articulaciones visibles. No se etiqueta a nadie como conductor: para una etapa posterior se deberá fijar y validar la región del asiento del conductor en la imagen. Si no hay resultado, verificar que `/health` responda y revisar que URL y token de pose sean los vigentes.

### C. Conectar la app Flutter

El proyecto móvil está en `IAM_yolo_native_app-main`. Flutter recibe la URL de Laravel por `API_BASE_URL`; no se configura la URL de Colab en la app.

Para teléfono Android conectado por USB, con Laravel ejecutándose en el PC:

```powershell
adb reverse tcp:8000 tcp:8000
Set-Location 'C:\laragon\www\Nueva carpeta\IAM_yolo_native_app-main'
flutter pub get
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000
```

En la app, entrar con **Operador Demo**, permitir la cámara y probar primero **Modo Consulta** (texto y foto). Después probar **Modo Caseta**, eligiendo una caseta existente.

Para instalarla en un teléfono sin USB o usar producción, compilar con la URL HTTPS de Laravel:

```powershell
flutter build apk --release --dart-define=API_BASE_URL=https://tu-subdominio
```

El APK compilado se genera en `build/app/outputs/flutter-apk/app-release.apk`; el APK anterior no incluye los cambios de esta continuación.

La carpeta `android/local.properties` contiene rutas locales del SDK. En esta máquina indica `C:\src\flutter`, que debe existir o reemplazarse por la ruta real de Flutter antes de compilar.

### D. Reglas y límites para la prueba

- Modo Caseta envía como máximo un frame por segundo.
- Laravel no crea pasos/alertas si no hay placa.
- La misma placa/caseta se registra y cobra una sola vez mientras siga presente; se libera después de **3 frames consecutivos sin placa**.
- Colab se desconecta por inactividad, tiene GPU/tiempo limitados y cambia su URL al reiniciar. Es válido para pruebas y demostración, no para disponibilidad permanente; para producción usar RunPod/VPS de inferencia y HTTPS estable.
- La API web no procesa Python en Hostinger. Laravel es el proxy/orquestador y guarda datos; Python vive en Colab/RunPod.

## 🧩 6. Cambios realizados en esta continuación

1. Flutter permite configurar `API_BASE_URL` en tiempo de compilación y limita el envío del modo Caseta a un frame por segundo. Ahora muestra errores de conexión/API y el estado `sin_placa`.
2. Laravel adjunta `X-API-Token` tanto al enviar fotos del agente como frames de caseta a `INFERENCE_URL/predict`. La configuración está en `services.inference` y en `.env.example`.
3. `PasosController` no crea pasos ni alertas cuando no hay placa, y mantiene una placa activa por caseta para no repetir el cobro por frames consecutivos. Tres lecturas seguidas sin placa liberan la caseta para el siguiente vehículo.
4. El servidor unificado valida el token de inferencia. Ya no carga por defecto un YOLO-pose humano como M3; acepta un YOLO-pose entrenado con keypoints de llantas y usa la caja del vehículo M2 para estimar ejes. Si M3 no está configurado, la respuesta usa una estimación explícita.
5. Se añadieron pruebas Laravel para estados del agente, proxy de imágenes, autenticación del servicio, no registrar frames vacíos y no cobrar repetidamente.
6. Se añadió `POSE_INFERENCE_URL` y `POSE_INFERENCE_API_TOKEN` como configuración separada. Laravel expone `/api/v1/pose/analizar`, adapta la imagen al servicio Colab y procesa la salida COCO; Flutter incluye la acción independiente **Analizar pose de personas**. No se clasifica al conductor ni se afecta el flujo de peaje.

**Validación de la integración de pose:** 2 pruebas dirigidas pasaron (10 aserciones), cubriendo el procesamiento de keypoints y el caso sin personas. No se probó la conexión real a Colab. Flutter no está disponible en esta máquina, así que no se pudo compilar ni ejecutar la pantalla; corregir `android/local.properties` con la ruta real de Flutter antes de compilar.

### Estado de inferencia al 24/11/2026

- Se ejecutó la inferencia local de las 19 imágenes de `placas-web/img/DATASEET` con `fast-plate-ocr`, usando `C:\laragon\bin\python\python-3.13\python.exe`.
- Los resultados están en `placas-web/storage/app/inferencia_dataset.csv` y `placas-web/storage/app/inferencia_dataset.json`. Ambos se verificaron con 19 filas/resultados; el JSON no contiene imágenes base64. Las placas son predicciones del modelo y todavía no se contrastaron manualmente con etiquetas de verdad terreno.
- El entorno `C:\laragon\www\Nueva carpeta\placas-venv` no funciona en esta PC: `pyvenv.cfg` apunta al Python 3.11 de la cuenta anterior (`C:\Users\Matias\AppData\Local\Programs\Python\Python311\python.exe`). No se recreó ni borró el entorno y no se instalaron dependencias.
- Se creó `Notebooks/servidor_inferencia_peaje_colab.ipynb` para levantar la API unificada en Colab con GPU, subir por separado el servidor y el modelo M1, y opcionalmente cargar pesos M2/M3. El notebook valida CUDA/ONNX Runtime, solicita el token sin mostrarlo, inicia la API y genera una URL temporal mediante Cloudflare Tunnel.
- El notebook pasó validaciones locales de JSON y sintaxis de las celdas Python. **Aún no se ha ejecutado en Colab**, por lo que la disponibilidad de GPU, la carga real de modelos, el túnel y la conexión Laravel→Colab siguen pendientes.
- Próximo paso: ejecutar el notebook en Colab con GPU; subir los archivos desde sus rutas locales; configurar `INFERENCE_URL` con la URL temporal y `INFERENCE_API_TOKEN` con el mismo token en `placas-web/.env`; ejecutar `php artisan optimize:clear` y probar una imagen desde Laravel. No compartir el contenido de `.env` ni el token.
