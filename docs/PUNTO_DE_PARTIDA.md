# Punto de partida — 25/11/2026 (≈17:20, hora Bolivia)

Estado congelado del sistema de peaje para poder volver a él si algo se rompe.

## Dónde está guardado

| Qué | Dónde |
|---|---|
| Todo el código (web, app, inferencia, notebooks, docs) | Repositorio Git local en `C:\laragon\www\Nueva carpeta`, commit `5903272`, etiqueta `punto-de-partida-2026-11-25` (sin secretos) |
| Respaldo del VPS (BD peaje, BD Cámara, código + `.env`, nginx, certificados) | VPS: `/root/respaldos/punto-de-partida-20261125-2118/` · Copia en la PC: `C:\laragon\www\Nueva carpeta\respaldos_vps\punto-de-partida-20261125-2118\` (**contiene secretos: no subir ni compartir**; está en `.gitignore`) |
| APK release | `C:\laragon\www\Nueva carpeta\peaje-release.apk` (y en `IAM_yolo_native_app-main\`), firmado con la clave debug de esta PC (SHA-1 `C9:9D:D9:7E:B5:5C:7D:37:04:47:7D:6F:70:61:DA:BE:1E:C9:7E:0A`) |
| Modelos entrenados | Google Drive (cuenta principal): `MyDrive/peaje_modelos/m2_best.pt`, `llantas_best.pt`, `runs_m2/`, `runs_m3/`, `evidencias_m2/`, `evidencias_m3/` |
| Datasets | Roboflow `matias-dario-mercier-rengel/car-motorcycle-bus-truck-ghnhh-gu1ay` v1 (M2) y `matias-dario-mercier-rengel/llantas-ejes` v1 (M3) |
| Respaldos previos a la dockerización de la Cámara | VPS `/root/respaldos/20261125-0312/` y `.../-final/` |

## Qué funciona en este punto

- **Web del peaje** https://peaje.xn--camaracomercioespaola-tbc.tech — Docker (`/var/www/peaje/placas-web`, compose `peaje`: app, web en 127.0.0.1:8081, MySQL 8.4), HTTPS Let's Encrypt, login Google con lista blanca y roles, Dashboard SSE en tiempo real (corregido), alertas, agente, página **Placas filtradas (2…2 · M)**.
- **Web de la Cámara** https://xn--camaracomercioespaola-tbc.tech — Docker (`/var/www/camara-esp`, compose `camara-esp`, 127.0.0.1:8080, PostgreSQL 18). php-fpm y PostgreSQL del host detenidos.
- **App Flutter**: login Google, Modo Caseta (cámara, casetas desde la API, placa/categoría/ejes/tarifa/estado, latencias, MISMO VEHÍCULO, FILTRO ✓), Modo Consulta, 401 → login, cerrar sesión. Probada en Galaxy A52: registra pasos reales (p. ej. 2352LMP).
- **Backend**: extracción de placa boliviana (3–4 dígitos + 3 letras) del texto del OCR, descarte de lecturas basura, deduplicación por similitud (≤ 2 caracteres) y 8 frames sin placa para liberar la caseta, filtro 2…2·M, log de diagnóstico por frame (`storage/logs/laravel.log`, LOG_LEVEL=info).
- **Inferencia** (Colab): M1 ONNX + EasyOCR, M2 `m2_best.pt` (YOLO11n-seg), M3 `llantas_best.pt` (YOLO-pose). **Se apaga cuando Colab se desconecta**: hay que volver a ejecutar `servidor_inferencia_peaje_colab.ipynb` y actualizar `INFERENCE_URL` en `/var/www/peaje/placas-web/.env` + `docker compose restart app`.

## Pendiente en este punto

1. Levantar de nuevo el servidor de Colab (la URL anterior murió) y actualizar `INFERENCE_URL`.
2. Subir a Drive el `inferencia/servidor_unificado.py` nuevo (OCR mejorado).
3. Opcional: OCR fast-plate-ocr + votación entre frames.
4. Limpiar los pasos de prueba antes del video (requiere confirmación).
5. Entregables: repositorio remoto, video, informe PDF (prompt en `docs/PROMPT_INFORME_TECNICO.md`), carpeta Drive.

## Cómo volver a este punto

**Código (PC):**
```powershell
Set-Location "C:\laragon\www\Nueva carpeta"
git status                                  # ver qué cambió desde el punto de partida
git diff punto-de-partida-2026-11-25        # detalle de los cambios
git checkout punto-de-partida-2026-11-25 -- <archivo>   # restaurar un archivo concreto
```

**Base de datos del peaje (VPS):**
```bash
cd /var/www/peaje/placas-web
D=/root/respaldos/punto-de-partida-20261125-2118
RP=$(grep ^MYSQL_ROOT_PASSWORD= docker/db.env | cut -d= -f2)
docker compose exec -T -e MYSQL_PWD="$RP" db mysql -uroot < $D/peaje.sql
```

**Base de datos de la Cámara (VPS):**
```bash
cd /var/www/camara-esp
U=$(grep ^POSTGRES_USER= docker/db.env | cut -d= -f2-); B=$(grep ^POSTGRES_DB= docker/db.env | cut -d= -f2-)
docker compose exec -T db pg_restore -U "$U" -d "$B" --clean --if-exists --no-owner < /root/respaldos/punto-de-partida-20261125-2118/camaraesp.dump
```

**Código/config del peaje en el VPS:** `tar xzf peaje-codigo-y-config.tgz -C /var/www/peaje` y luego `docker compose up -d --build` en `/var/www/peaje/placas-web`.
