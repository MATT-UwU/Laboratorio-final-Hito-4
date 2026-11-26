# Prompt para Claude (chat): redactar el Informe Técnico del Laboratorio Final de Hito

> **Cómo usarlo:** copia TODO este archivo y pégalo en un chat nuevo de Claude. Adjunta además (si los tienes) las capturas de métricas de Colab (`results.png`, `confusion_matrix_normalized.png`), los archivos `metricas_m2.json` / `metricas_m2_por_clase.csv` de `MyDrive/peaje_modelos/evidencias_m2/`, las métricas de M3 y capturas del panel. Antes de pegar, reemplaza los campos marcados con `⟦…⟧` con tus datos reales (o déjalos así: Claude pondrá valores provisionales marcados "(prov.)" para reemplazarlos luego).

---

## Tu rol y tarea

Eres un ingeniero de Machine Learning y desarrollo de software que redacta informes técnicos universitarios de alto nivel. Redacta el **Informe Técnico completo** del *Laboratorio Final de Hito: Sistema Inteligente de Control de Peaje* (materia **IAM-811 Machine Learning y Aprendizaje Profundo**, UNIFRANZ, docente Álvaro Pinto), en **español**, con ortografía y tildes correctas, tono académico-técnico, en **Markdown** listo para convertir a PDF (Word/Google Docs o Pandoc).

### Reglas obligatorias
1. **Datos reales y valores provisionales.** Todo lo descriptivo (arquitectura, despliegue, API, BD, agente, app, problemas) debe salir solo de este documento y de los adjuntos. Para los datos numéricos que todavía no están disponibles (métricas de M2/M3, precisión del OCR, error de ejes, latencias, tiempos de entrenamiento, conteos marcados con `⟦…⟧`), usa **valores provisionales razonables** y márcalos SIEMPRE como provisionales, sin presentarlos como medidos:
   - En el texto y en cada celda de tabla, añade el sufijo **"(prov.)"** a cada valor provisional, por ejemplo `0,78 (prov.)`.
   - Al inicio de las secciones 7, 8 y 13 incluye un recuadro de aviso: *"Los valores marcados (prov.) son provisionales, pendientes de la evaluación final del modelo en Colab, y se reemplazarán por los medidos."*
   - Mantén los valores provisionales coherentes entre sí y con la literatura (por ejemplo, un YOLO11n-seg entrenado 30 épocas sobre ~5.600 imágenes con pseudo-máscaras de SAM suele quedar por debajo del YOLO11n-seg oficial en COCO; latencias por frame del orden de cientos de ms a pocos segundos por el túnel a Colab).
   - Si un adjunto trae el valor real, usa el real sin "(prov.)".
2. Cubre **todos** los puntos que exige el enunciado para el informe: arquitectura; datasets (cantidad de imágenes, clases, aumentaciones); entrenamiento y métricas (mAP de M2 y M3, precisión del OCR, error en el conteo de ejes); latencia de punta a punta; mecanismo de tiempo real; problemas encontrados y soluciones.
3. Incluye **diagramas en Mermaid** (arquitectura, secuencia del flujo de un paso, modelo entidad-relación) y **tablas** donde ayuden.
4. Extensión objetivo: **15–25 páginas** equivalentes. Desarrolla cada sección con profundidad (justificación de decisiones, no solo descripción).
5. No incluyas secretos (tokens, API keys, client secret, contraseñas). Menciona solo los nombres de las variables de entorno.
6. Al final, entrega una **lista de verificación** con cada valor "(prov.)" y cada dato `⟦…⟧` usado, indicando de dónde se obtiene el valor real (archivo de Colab, captura de la app, etc.) para reemplazarlo antes de la entrega.

---

## Datos de la portada
- Universidad: UNIFRANZ — Carrera: ⟦carrera⟧
- Materia: IAM-811 Machine Learning y Aprendizaje Profundo
- Título: *Sistema Inteligente de Control de Peaje basado en Visión por Computadora*
- Laboratorio Final de Hito (Laboratorio 3) — Entrega: 25/11/2026
- Integrante: Matías Darío Mercier Rengel (desarrollo completo: datasets, entrenamiento, inferencia, web, agente, app móvil, despliegue)
- Docente: Álvaro Pinto
- Enlaces: aplicación web **https://peaje.xn--camaracomercioespaola-tbc.tech** (dominio IDN: peaje.camaracomercioespañola.tech) · Repositorio: https://github.com/MATT-UwU/Laboratorio-final-Hito-4 · Carpeta de evidencias: ⟦URL de Drive⟧ · Dataset M2 Roboflow: workspace `matias-dario-mercier-rengel`, proyecto `car-motorcycle-bus-truck-ghnhh-gu1ay`, versión 1 · Dataset M3 Roboflow: `matias-dario-mercier-rengel/llantas-ejes` v1 (39 imágenes)

---

## Estructura requerida del informe

1. **Portada**
2. **Resumen ejecutivo** (½ página: problema, solución, resultados principales con marcadores de métricas)
3. **Introducción y objetivos** (general y específicos, tomados del enunciado)
4. **Arquitectura del sistema**
   - Diagrama Mermaid de componentes (App móvil → Web/API en VPS → Endpoint de inferencia en Colab; Google OAuth; base de datos; SSE).
   - Tabla "componente → tecnología → dónde corre" (demostrar que **ningún componente corre en la PC**).
   - Diagrama de secuencia Mermaid del flujo de un paso: frame → `POST /api/v1/pasos/frame` → `INFERENCE_URL/predict` → registro del paso → alerta → evento SSE → panel.
   - Justificación del desacople por `INFERENCE_URL` (cambiar Colab↔RunPod sin tocar código).
5. **Infraestructura y despliegue**
6. **Datasets** (M1, M2, M3: origen, cantidades, clases, splits, preprocesamiento, aumentaciones, mapeo de clases)
7. **Modelos, entrenamiento y métricas** (M1+OCR, M2, M3; hiperparámetros; tablas de métricas; análisis)
8. **Endpoint de inferencia unificado** (contrato JSON, latencias)
9. **Aplicación web, API REST y base de datos** (rutas, autenticación, roles, lista blanca, lógica de estados, tarifas y alertas; diagrama ER Mermaid)
10. **Tiempo real** (mecanismo SSE, por qué SSE, configuración nginx sin buffer)
11. **Agente conversacional** (4 estados, normalización, sugerencias OCR, consulta por imagen, no inventa datos)
12. **Aplicación móvil** (login Google, Modo Caseta, Modo Consulta, conversión de frames, manejo de sesión)
13. **Pruebas de punta a punta y latencia** (metodología y tabla de resultados con marcadores)
14. **Problemas encontrados y soluciones** (tabla amplia — ver lista abajo)
15. **Seguridad y buenas prácticas**
16. **Conclusiones y trabajo futuro**
17. **Referencias** (Ultralytics YOLO11, SAM 2, Roboflow, Laravel, Flutter, Google Identity, EasyOCR/fast-plate-ocr, MDN SSE)
18. **Anexos** (estructura del repositorio, variables de entorno sin valores, comandos de despliegue, colección Postman)

---

## Información técnica del proyecto (fuente de verdad)

### Arquitectura y dónde corre cada parte
| Componente | Tecnología | Dónde corre |
|---|---|---|
| App móvil | Flutter 3.50 (Dart), `camera`, `google_sign_in`, `image`, `http`, `image_picker` | Teléfono Android (APK release) |
| Aplicación web + API + agente | Laravel 12 (PHP 8.3), Blade + Tailwind | VPS Hostinger (Ubuntu 26.04, 2 vCPU, 8 GB RAM) en **Docker** |
| Base de datos | MySQL 8.4 | Contenedor Docker en el VPS (volumen persistente) |
| Tiempo real | Server-Sent Events (`GET /pasos/stream`) | VPS |
| Proxy y HTTPS | nginx del host + Let's Encrypt (certbot, renovación automática) | VPS |
| Inferencia M1+M2+M3 | FastAPI + Uvicorn, ONNX Runtime, Ultralytics, EasyOCR, expuesto con túnel Cloudflare (`trycloudflare.com`) | Google Colab (GPU T4) |
| Entrenamiento | Notebooks de Colab (GPU T4) | Google Colab |
| Datasets | Roboflow (proyecto de detección/segmentación de vehículos; proyecto de keypoints de llantas) | Roboflow |
| Autenticación | Google OAuth 2.0 (cliente Web + cliente Android) | Google Cloud |
| Modelos entrenados y evidencias | Google Drive `MyDrive/peaje_modelos/` | Nube |

### Despliegue en el VPS (Docker)
- Proyecto Compose `peaje` en `/var/www/peaje/placas-web` con 3 servicios: **app** (imagen PHP 8.3-FPM con extensiones pdo_mysql, bcmath, intl, zip, exif, pcntl, gd, opcache), **web** (nginx interno publicado solo en `127.0.0.1:8081`) y **db** (MySQL 8.4 con volumen `mysqldata` y healthcheck).
- Dockerfile multi-etapa: base PHP → `vendor` (composer install --no-dev) → imagen final; el `.env` se monta en tiempo de ejecución (no va dentro de la imagen); `storage/` se monta como volumen.
- php-fpm con `pm.max_children = 20` porque cada panel abierto mantiene una conexión SSE que ocupa un proceso.
- nginx interno: `location = /pasos/stream` con `fastcgi_buffering off` y `fastcgi_read_timeout 600s`; resto con timeout 150 s (la inferencia en Colab puede tardar). Resolver DNS de Docker (`127.0.0.11`) para tolerar reinicios del contenedor.
- nginx del host: server block del subdominio con `proxy_pass http://127.0.0.1:8081`, `proxy_buffering off` en `/pasos/stream`, `client_max_body_size 20M`, cabeceras `X-Forwarded-Proto`/`X-Real-IP`; HTTPS con certbot y redirección 80→443.
- Migraciones y seeders ejecutados con `php artisan migrate --seed`.
- El mismo VPS aloja otro sitio en producción (también dockerizado en un proyecto Compose aparte), aislado por puertos internos y server blocks propios.

### Base de datos (6 tablas mínimas + soporte)
- `users` (email Google, nombre, `rol` admin/operador, `activo`, `google_id`, avatar) — lista blanca.
- `vehiculos` (placa, propietario, categoria, ejes, saldo, activo) — **20 vehículos de prueba** sembrados (ej.: 2M12 auto 100 Bs, 2BM2 moto, 2XM72 minibús, 2CM52 camión 3 ejes, 2DM42 bus, 2HM82 camión 4 ejes, 2IM62 auto con saldo 0 para probar "sin saldo").
- `casetas` (3: Norte, Sur, Este).
- `tarifas` (categoría, ejes, monto): moto 2e 5 Bs · auto 2e 10 Bs · minibús 2e 15 Bs · camión 2e 20 / 3e 30 / 4e 40 Bs · bus 2e 18 / 3e 25 Bs.
- `pasos` (placa_leida, placa_normalizada, confianza_ocr, categoria_detectada, ejes_detectados, monto, caseta_id, usuario_id, imagen_url, estado, inferencia_raw JSON, created_at).
- `alertas` (paso_id, tipo `no_registrado`/`sin_saldo`/`evasion`, atendida).
- Administrador docente precargado: `doc.alvaro.pinto.ma@unifranz.edu.bo` (rol admin).

### API REST (prefijo `/api/v1`, documentada en colección Postman `docs/api_peaje_postman_collection.json`)
| Método | Ruta | Descripción |
|---|---|---|
| POST | `/auth/google` | Recibe el **idToken** de Google, lo valida con el endpoint oficial `oauth2.googleapis.com/tokeninfo` y contra la lista blanca, y devuelve un token Bearer propio del sistema (aleatorio, guardado en caché del servidor con vigencia de 30 días). Única ruta pública. |
| POST | `/pasos/frame` | Recibe un frame (multipart `file` + `caseta_id`), llama a la inferencia, calcula tarifa y estado, guarda evidencia, crea alerta si corresponde y emite el evento SSE |
| POST | `/placas/consultar` | Estado de una placa por texto |
| POST | `/placas/consultar-imagen` | Estado de una placa por imagen (M1 + OCR) |
| GET | `/placas/{placa}/historial` | Historial paginado de pasos |
| GET | `/casetas` | Lista de casetas (usada por la app para el selector) |
| GET | `/casetas/{id}/resumen` | Conteo, recaudación y alertas del día |
| POST | `/agente/chat` | Mensaje al agente conversacional |
| POST | `/pose/analizar` | Extra opcional (no parte de la rúbrica) |
| CRUD | `/vehiculos` | API resource |
Todas, excepto `/auth/google`, exigen `Authorization: Bearer <token>` (middleware `api.token`); un token inválido devuelve **401**.

### Lógica de negocio en `POST /pasos/frame`
1. Llama a `INFERENCE_URL/predict` con cabecera `X-API-Token` (secreto compartido `INFERENCE_API_TOKEN`).
2. Si no hay placa → responde `estado: sin_placa` (no registra paso).
3. **Anti-duplicado:** si la misma placa sigue presente en la caseta (estado en caché con TTL), devuelve el paso existente con `duplicado: true` y **no cobra dos veces**.
4. Calcula el monto con `Tarifa::montoPara(categoría, ejes)`.
5. Estado: `ok` (registrado con saldo) · `no_registrado` · `sin_saldo`.
6. Guarda la imagen de evidencia en `storage/app/public/evidencias` (URL pública `/storage/evidencias/...`).
7. Descuenta saldo si `ok`; crea **alerta** si `no_registrado` o `sin_saldo`.
8. Emite el evento al archivo de eventos SSE.

### Tiempo real (SSE)
- `GET /pasos/stream` (autenticado) mantiene la conexión abierta y emite cada paso y alerta nuevos (sondeo interno del archivo de eventos cada 0,5 s); el dashboard actualiza la tabla de pasos, los contadores de recaudación y alertas **sin recargar**.
- Se eligió SSE frente a WebSockets porque el flujo es unidireccional servidor→navegador, no requiere servidor adicional (Reverb/Pusher) ni servicios de terceros, y funciona sobre HTTP/HTTPS estándar con nginx configurado sin buffer.

### Autenticación y roles
- Web: flujo OAuth 2.0 *authorization code* implementado en `AuthController` (redirección a `accounts.google.com`, intercambio del código en `oauth2.googleapis.com/token` y lectura del perfil en `userinfo`), cliente "Aplicación web", redirect `https://peaje.xn--camaracomercioespaola-tbc.tech/auth/google/callback`). `DEMO_LOGIN=false` en producción: **solo Google**.
- App: Google Sign-In con `serverClientId` = Client ID web; envía el **idToken** a `/api/v1/auth/google` (se eliminó el uso de accessToken como respaldo porque el backend solo valida idToken). Cliente OAuth Android registrado con paquete `com.example.yolo_native_app` y SHA-1 del certificado de firma.
- Lista blanca en `users` (`activo = true`); cuentas no registradas ven "acceso denegado" (**verificado en producción**: login correcto con cuenta autorizada y rechazo de cuenta no autorizada).
- Roles: **admin** (CRUD vehículos, tarifas, casetas; middleware `role:admin`) y **operador** (registra pasos, consulta placas).

### Agente conversacional
- Implementado en el backend (Laravel, `AgenteController`) con **herramientas deterministas sobre la base de datos** (tipo function-calling: la intención "consultar placa" ejecuta consultas SQL; no hay generación libre, por lo que **no inventa datos**).
- Normaliza la placa: mayúsculas, sin guiones ni espacios, validación de formato.
- Responde los **4 estados**: `registrada_con_paso` (fecha, caseta, categoría, monto del último paso) · `registrada_sin_paso` · `identificada_no_registrada` (detectada por cámara sin registro → posible evasión) · `no_existe`.
- Sugerencias "¿Quisiste decir…?" generando variantes por confusiones típicas de OCR: O↔0, I↔1, B↔8, S↔5.
- Consulta por imagen: envía la foto al endpoint de inferencia (M1+OCR); si no hay placa legible responde `sin_placa` y pide otra imagen.
- Disponible en web (`/chat`) y en la app (Modo Consulta), siempre autenticado.

### App móvil (Flutter)
- Login exclusivo con Google; banner rojo si la URL de la API es local/HTTP (la rúbrica prohíbe recursos locales). URL por defecto: el subdominio HTTPS.
- **Modo Caseta:** cámara en `startImageStream` (YUV420); se limita a **un frame cada 400 ms (≈2,5 fps)** comprobando el intervalo *antes* de convertir; conversión YUV420→JPEG en un **isolate** (`compute`) para no bloquear la UI, usando `bytesPerRow` y `bytesPerPixel` (pixel stride UV), reducción a **960 px** de ancho, rotación según `sensorOrientation` y orientación del dispositivo (pantalla `sensorLandscape`), JPEG calidad 80. Timeout 20 s para `/pasos/frame` y 30 s para el resto.
- Panel inferior: placa, categoría, ejes, tarifa, estado (colores: ok verde, sin saldo naranja, no registrado/alerta/sin conexión rojo, sin placa gris), etiqueta **"MISMO VEHÍCULO"** si `duplicado`, y latencias **"Colab: X ms · Total: Y ms"** (tiempo total medido con `Stopwatch`; `latencia_ms` reportada por el endpoint).
- Selector de caseta cargado desde `GET /api/v1/casetas` (con respaldo 1–3).
- **Modo Consulta:** placa por texto o foto (cámara/galería) al agente.
- Manejo de sesión: cualquier **401** vuelve al login ("Sesión expirada, inicia sesión de nuevo"); botón de cerrar sesión.
- APK release (≈50 MB) compilado con `--dart-define=API_BASE_URL=...` y `GOOGLE_WEB_CLIENT_ID=...`; Gradle 9.5, Android Gradle Plugin 9.3.1, Kotlin 2.4.20, compileSdk 36, minSdk 24.

### Endpoint de inferencia unificado (Colab)
- Notebook `servidor_inferencia_peaje_colab.ipynb`: carga modelos desde `MyDrive/peaje_modelos/`, pide el token de forma oculta (`getpass`), levanta **FastAPI** y lo publica con **Cloudflare Tunnel**; imprime `INFERENCE_URL` que se configura en el `.env` del VPS.
- `POST /predict` (cabecera `X-API-Token`) devuelve JSON con **placa, confianza, categoría, ejes y latencia_ms** (además de cajas y detalles por modelo). `GET /` = estado/salud (lo consume la página `/estado`).
- **M1 – Placas + OCR:** detector **YOLOv9-t 384 end-to-end exportado a ONNX** (`yolo-v9-t-384-license-plates-end2end.onnx`, reutilizado del proyecto anterior), ONNX Runtime con CUDA; preprocesamiento letterbox 384 con relleno 114; recorte de la placa y OCR con **EasyOCR** (es/en) en GPU, con Tesseract como respaldo; normalización del texto.
- **M2 – Categoría:** YOLO11-seg entrenado (ver abajo); mapea clases a `moto, auto, minibus, camion, bus`; devuelve caja y área relativa de la máscara del vehículo principal.
- **M3 – Ejes:** YOLO-pose entrenado con keypoints de llantas; asocia llantas a la caja del vehículo de M2 y cuenta ejes; si no hay vista lateral o el modelo no detecta, estima por categoría (camión/bus 3, resto 2) y lo indica en `metodo`.

### Dataset y entrenamiento de M2 (segmentación de instancias)
- Fuente: dataset público de Roboflow Universe **"car-motorcycle-bus-truck"** (workspace original `new-workspace-heijb`), **forkeado** al workspace propio `matias-dario-mercier-rengel` → proyecto `car-motorcycle-bus-truck-ghnhh-gu1ay`, versión 1 ("cars-v1"). **6.627 imágenes** en el proyecto; ⟦COMPLETAR: imágenes train/valid/test⟧ (la descarga mostró **5.600 imágenes de train**).
- Clases originales (8): `car, motorcycle, n-lcv, o-bus, p-ambulance, p-truck, rickshaw123, tractor123`. **Mapeo a las 5 categorías de tarifa:** car→auto · motorcycle→moto · rickshaw123→moto · n-lcv→minibús · p-ambulance→minibús · o-bus→bus · p-truck→camión · tractor123→camión.
- Preprocesamiento Roboflow: Resize 640×640 ⟦COMPLETAR: aumentaciones aplicadas en la versión, si las hubo⟧.
- **Problema y solución clave:** el proyecto de Roboflow es de tipo *Object Detection*; la exportación YOLOv11 trajo **7.764 cajas y solo 685 polígonos**, y Roboflow no permite exportar `coco-segmentation` para proyectos de detección. Como la rúbrica exige **segmentación de instancias**, el notebook genera las máscaras automáticamente con **SAM 2.1 (base)** usando cada caja como *prompt*: máscara → contorno externo (`cv2.findContours`) → polígono simplificado (`approxPolyDP`, ε = 0,2 % del perímetro) → etiqueta YOLO-seg. Así todo el dataset quedó en formato de polígonos.
- Entrenamiento: **YOLO11n-seg** preentrenado en COCO, 30 épocas, `imgsz=640`, `batch=16`, `patience=10` (parada temprana), GPU **T4** de Colab; pesos guardados en Drive en cada época para reanudar ante desconexiones (`resume=True`). Tiempo: ⟦COMPLETAR⟧ min.
- Métricas (de `metricas_m2.json`): Box mAP50 ⟦⟧ · Box mAP50-95 ⟦⟧ · **Mask mAP50 ⟦⟧ · Mask mAP50-95 ⟦⟧** · Precisión ⟦⟧ · Recall ⟦⟧ · Velocidad de inferencia ⟦⟧ ms/img. Tabla por clase ⟦de metricas_m2_por_clase.csv⟧. Analiza la matriz de confusión (`confusion_matrix_normalized.png`) y las curvas de `results.png`.
- Limitación a discutir honestamente: el enunciado pide ampliar el dataset con imágenes propias; por tiempo, ⟦COMPLETAR: indicar si se agregaron imágenes propias o dejarlo como trabajo futuro⟧. Las máscaras generadas por SAM son pseudo-etiquetas (posible ruido en bordes).

### Dataset y entrenamiento de M3 (keypoints de llantas)
- Notebook `keypoints_llantas_yolo_detectron2_v3.ipynb`, sección 8: imágenes de vehículos en vista lateral ⟦COMPLETAR: origen y cantidad⟧ → **auto-etiquetado** (YOLOE zero-shot para llantas + keypoints geométricos) → corrección en Roboflow (proyecto *Keypoint Detection*, clase `llanta`, **5 keypoints**: centro, contacto, superior, izquierda, derecha) → versión ⟦⟧ → entrenamiento **YOLO11s-pose**, 80 épocas, imgsz 640, batch 16. Guardado como `MyDrive/peaje_modelos/llantas_best.pt`.
- Conteo de ejes: llantas asociadas a la caja del vehículo, agrupadas por posición horizontal → número de ejes.
- Métricas: Pose mAP50 ⟦⟧ · Box mAP50 ⟦⟧ · **error en el conteo de ejes** sobre ⟦N⟧ imágenes de prueba: MAE ⟦⟧, exactitud ⟦⟧ %.
- (El notebook incluye además comparación con Detectron2 Keypoint R-CNN — mencionarla solo si se ejecutó: ⟦COMPLETAR⟧.)

### M1 – Evaluación del OCR
- Reutilizado del Laboratorio anterior (comparación de 3 entornos: venv + fast-plate-ocr, conda + Tesseract, mamba + EasyOCR, sobre 20 imágenes con placa real conocida de Bahrain, Irlanda, Noruega y EE. UU.). ⟦COMPLETAR: aciertos/precisión de cada OCR⟧. En el endpoint de producción se usa EasyOCR en GPU.

### Latencia de punta a punta
- Medida en la app: **Total** (captura → respuesta del VPS) y **Colab** (`latencia_ms` interna del endpoint). Tabla con ⟦≥10 mediciones⟧: mínimo, promedio, máximo, p95. Descomponer: conversión en el teléfono, red teléfono→VPS, VPS→Colab (túnel Cloudflare), inferencia (M1+M2+M3), registro + SSE. ⟦COMPLETAR valores⟧. Tiempo desde el registro hasta que aparece en el panel por SSE: ⟦⟧ (≤ 0,5 s por diseño del sondeo).

### Problemas encontrados y soluciones (desarrollar como tabla y comentar los más relevantes)
1. **VPS compartido con otro sitio en producción** → todo se dockerizó en proyectos Compose separados, cada uno publicado solo en `127.0.0.1` y expuesto por el nginx del host con su server block y certificado; respaldos previos (pg_dump, configuración nginx, storage) y cambio sin downtime.
2. **SSE detrás de dos proxies** (nginx del host + nginx del contenedor) → `proxy_buffering off` y `fastcgi_buffering off` en `/pasos/stream`, timeouts largos, y más procesos php-fpm (`pm.max_children=20`).
3. **502 por cabeceras grandes** de Laravel (Link de precarga) a través del proxy → `proxy_buffer_size 128k` / `proxy_buffers`.
4. **nginx del contenedor guardaba la IP vieja** del contenedor `app` tras reiniciarlo → resolver DNS de Docker y `fastcgi_pass` con variable.
5. **`composer.lock` desactualizado** (faltaban Sanctum y Socialite) → regeneración del lock con PHP 8.3 en contenedor.
6. **Google OAuth no acepta IPs** como URI de redirección → subdominio con HTTPS (Let's Encrypt).
7. **App: frames convertidos en el hilo de UI** (congelamiento) e índice UV incorrecto (colores corruptos) → isolate con `compute`, `uvPixelStride`, descarte de frames *antes* de convertir, reducción a 960 px y rotación correcta; timeout del frame de 4 s → 20 s.
8. **Cobro duplicado del mismo vehículo** en frames consecutivos → caché de presencia por caseta y bandera `duplicado`.
9. **Compilación del APK:** el JDK 25 de Android Studio no era compatible con Gradle 8.14; Flutter 3.50 exige Gradle ≥ 9.3.1/9.5 y AGP ≥ 9.1.1 → migración a Gradle 9.5, AGP 9.3.1, Kotlin 2.4.20 (`kotlin { compilerOptions }`); conflicto de *namespace* de TensorFlow Lite con AGP 9 → se retiró el detector YOLO nativo (la inferencia corre en la nube; el código quedó respaldado), y release sin minificar.
10. **Sesiones expiradas en la app** → manejo global de 401 y botón de cierre de sesión.
11. **Dataset de M2 sin polígonos** (proyecto de detección) → máscaras generadas con SAM 2.1 a partir de las cajas.
12. **Desconexiones de Colab gratuito** durante entrenamientos largos → checkpoints por época en Drive y reanudación.
13. **URL del túnel de Colab cambia en cada reinicio** → `INFERENCE_URL` en `.env` (cambio de una variable + reinicio del contenedor), página `/estado` para verificarlo.

### Seguridad
- Secretos solo en `.env` del servidor (montado en el contenedor, fuera de la imagen y del repositorio); en el repositorio solo `.env.example`. `.gitignore` excluye `.env`, `key.properties`, `*.jks`, `*.keystore`.
- HTTPS obligatorio; `APP_DEBUG=false`, `APP_ENV=production`; endpoint de inferencia protegido con `X-API-Token`; API con Bearer en todas las rutas salvo login; lista blanca y roles; MySQL no expuesto a Internet (solo red interna de Docker).
- Variables de entorno relevantes (sin valores): `APP_URL, DB_*, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI, INFERENCE_URL, INFERENCE_API_TOKEN, DEMO_LOGIN`.

### Estructura del repositorio (anexo)
```
/Notebooks   entrenamiento M2, M3 y servidor de inferencia (Colab)
/inferencia  servidor_unificado.py (FastAPI M1+M2+M3), Dockerfile, requirements.txt
/placas-web  aplicación web Laravel (API, panel SSE, agente) + Dockerfile y docker-compose.yml
/IAM_yolo_native_app-main  app móvil Flutter
/docs        DESPLIEGUE_VPS.md, colección Postman, este prompt
README.md, .env.example
```

---

## Formato de salida
- Un único documento Markdown, con títulos numerados, tablas, bloques Mermaid y bloques de código cortos (solo configuraciones clave: server block SSE, fragmento del docker-compose, contrato JSON de `/predict`, ejemplo de respuesta del agente para cada estado).
- Redacción en prosa técnica, en tercera persona o impersonal, sin viñetas excesivas en las secciones de análisis.
- Termina con: (1) conclusiones numeradas vinculadas a cada objetivo específico, indicando su grado de cumplimiento; (2) trabajo futuro; (3) la **lista de valores provisionales "(prov.)" y datos ⟦…⟧ a reemplazar**.

---

## Resultados ya medidos (usar estos valores reales, sin "(prov.)")

### Endpoint de inferencia en Colab (25/11/2026)
- `GET /` respondió: `estado: activo`, `dispositivo: cuda`, `m1_cargado`, `m2_cargado`, `m3_cargado` y `ocr_disponible` en `true` (GPU T4).
- `POST /predict` con token inválido → **401**; sin imagen → **422**.
- Prueba con 20 imágenes de referencia (carpeta `INFERENCIA`, placas reales conocidas de Bahrain, Irlanda, Noruega y EE. UU.):
  - **Latencia interna del endpoint (M1+M2+M3): 60–156 ms por imagen, mediana ≈ 87 ms.**
  - **OCR exacto: 5/20 (25 %)** — 73049, 32789, 172G2064, KH90284, ZE61026. **6/20 (30 %)** si se cuenta la placa contenida dentro de un texto más largo (XK30060ORTAIO).
  - Errores típicos: el OCR concatena texto del marco/estado (p. ej. "VIRGINIA…", "MASSACHUSETTS…", "IRL…"), confusiones de caracteres (O↔0, 3↔9 en "7K21710" vs ZH21710) y 2 imágenes sin placa detectada. Esto justifica la normalización y las sugerencias "¿Quisiste decir…?" del agente, y como trabajo futuro: filtrar el texto por la caja de la placa y por patrón de formato.
  - M2 clasificó 19/20 como auto y 1 como camión (Ireland_895907; verificar visualmente cuál es correcto); M3 devolvió 2 ejes en las 20 (método `keypoints_llantas`). Son fotos de placas, no de costado, así que no sirven para medir el error de ejes: medirlo con fotos laterales propias.
- Métricas de entrenamiento de M2 observadas a la época 5/30 (validación, 638 imágenes, 899 instancias): Box P 0,735 · R 0,558 · mAP50 0,611 · mAP50-95 0,471 · Mask P 0,741 · R 0,551 · **mAP50 0,601 · mAP50-95 0,430**. Usar las de la última época / `metricas_m2.json` si se adjuntan.
- M3 (YOLO-pose de llantas): dataset propio en Roboflow `matias-dario-mercier-rengel/llantas-ejes` v1, **39 imágenes** (fotos propias + Universe), 5 keypoints por llanta, resize 640×640; auto-etiquetado con el notebook y corregido manualmente en Roboflow.
