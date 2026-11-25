# Peaje Inteligente — Sistema de Control de Peaje

## Integrantes
- **Matias Dario Mercier Rengel** — Full Stack + Integración AI

## Descripción
Sistema de control de peaje basado en visión por computadora que integra tres modelos de deep learning, una app móvil Flutter, una aplicación web Laravel con panel en tiempo real (SSE) y un agente conversacional.

## Arquitectura

```
App Móvil (Flutter)
       │  frames / Google Token
       ▼
Aplicación Web — placas-web/ (Laravel, subdominio HTTPS)
  ├─ /dashboard        → Panel tiempo real (SSE)
  ├─ /pasos            → Historial con imagen evidencia
  ├─ /vehiculos        → CRUD vehículos (admin)
  ├─ /alertas          → Alertas en tiempo real
  ├─ /chat             → Agente conversacional
  ├─ /estado           → Estado del endpoint de inferencia
  └─ /api/v1/...       → API REST (Sanctum)
       │
       ▼
INFERENCE_URL (RunPod o Colab)
  ├─ M1: Detección placas (YOLOv9 ONNX) + OCR (Tesseract)
  ├─ M2: Segmentación instancias (YOLO-seg) → categoría
  └─ M3: Keypoints ruedas (YOLO-pose) → ejes
```

## Tecnologías
| Capa | Tecnología |
|---|---|
| App móvil | Flutter (Dart) |
| Backend web | Laravel 12 (PHP 8.2) |
| Base de datos | MySQL (producción) / SQLite (desarrollo) |
| Autenticación | Google OAuth 2.0 (Laravel Socialite) |
| API móvil | Laravel Sanctum (token) |
| Tiempo real | Server-Sent Events (SSE) |
| Modelos AI | YOLOv9 ONNX, YOLO-seg, YOLO-pose |
| OCR | Tesseract |
| Entrenamiento | Google Colab |
| Inferencia nube | RunPod / Google Colab |
| Datasets | Roboflow |

## Instrucciones de despliegue

### 1. Aplicación Web (VPS Hostinger)

```bash
git clone <repo-url>
cd placas-web
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Editar .env con tus credenciales reales
php artisan key:generate
php artisan migrate --seed
chmod -R 775 storage bootstrap/cache
php artisan storage:link
php artisan optimize
```

### 2. Configurar Google OAuth en [Google Cloud Console](https://console.cloud.google.com)
1. Crear proyecto → Habilitar "Google Identity"
2. Credenciales → OAuth 2.0 → Tipo: Aplicación web
3. URI de redirección: `https://tusubdominio.com/auth/google/callback`
4. Pegar `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET` en `.env`

### 3. Endpoint de inferencia (RunPod)
Laravel no ejecuta Python ni carga los modelos. `INFERENCE_URL` debe apuntar a la URL pública base del servidor FastAPI iniciado desde Colab o RunPod; Laravel envía la imagen como multipart a `POST {INFERENCE_URL}/predict`. `INFERENCE_API_TOKEN` debe coincidir con el token configurado en el servidor Python y se envía en el header `X-API-Token`; no publiques ni subas ese secreto al repositorio.

El chat web (`/chat`) consulta el estado de la placa en Laravel y reenvía las imágenes por una ruta autenticada de la web. La app móvil puede seguir usando `POST /api/v1/placas/consultar-imagen` con su token de API y debe configurar `API_BASE_URL` para apuntar a Laravel, no a Colab.

Ver carpeta `/inferencia` para el contrato del servidor Python.

### 4. App Móvil (Flutter)
```bash
cd movil
flutter build apk --release
```

## API REST — Base URL: `/api/v1`

| Método | Ruta | Descripción |
|---|---|---|
| POST | /auth/google | Autenticar desde app móvil |
| POST | /pasos/frame | Registrar frame de caseta |
| POST | /placas/consultar | Consultar placa por texto |
| POST | /placas/consultar-imagen | Consultar placa por imagen |
| GET | /placas/{placa}/historial | Historial de pasos |
| GET | /casetas/{id}/resumen | Resumen diario de caseta |
| POST | /agente/chat | Chat con agente |
