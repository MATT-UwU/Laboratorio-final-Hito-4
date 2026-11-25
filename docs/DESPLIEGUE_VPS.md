# Despliegue: VPS Hostinger + Colab + App

Arquitectura de la demo (sección 5 de la rúbrica: nada corre en la PC):

```text
App Flutter ──HTTPS + Bearer──> Laravel en VPS (https://peaje.TU-DOMINIO)
                                   └──HTTPS + X-API-Token──> Colab (https://xxxx.trycloudflare.com)
```

Reemplaza en todo el documento:
- `peaje.TU-DOMINIO` por tu subdominio.
- `IP_DEL_VPS` por la IP que muestra hPanel.
- `TU_USUARIO/peaje` por tu repositorio de GitHub.

---

## 0. Orden recomendado

1. Subir el código a GitHub (paso 1).
2. Configurar el subdominio, el VPS y el HTTPS (pasos 2 a 4).
3. Configurar Google OAuth (paso 5).
4. Levantar Colab y pegar la URL en el VPS (paso 6).
5. Compilar el APK apuntando al subdominio (paso 7).
6. Revisar la lista de verificación (paso 8).

---

## 1. Subir el código a GitHub (desde la PC)

El `.gitignore` de la raíz ya excluye `.env`, `placas-venv`, los APK, `build/` de Flutter y los `*.pt`.

```powershell
Set-Location "C:\laragon\www\Nueva carpeta"
git init
git add .
git status            # REVISA que no aparezca ningún .env
git commit -m "Sistema de peaje: web, inferencia, movil y notebooks"
git branch -M main
git remote add origin https://github.com/TU_USUARIO/peaje.git
git push -u origin main
```

Crea el repositorio vacío en GitHub antes del push. Puede ser público o privado; si es privado, invita al docente.

---

## 2. Subdominio (hPanel de Hostinger)

En **Dominios → DNS / Nameservers** del dominio, agrega un registro:

| Tipo | Nombre | Apunta a | TTL |
|---|---|---|---|
| A | `peaje` | `IP_DEL_VPS` | 300 |

Comprueba desde la PC con `nslookup peaje.TU-DOMINIO`: debe devolver la IP del VPS. Puede tardar unos minutos.

---

## 3. Preparar el VPS (Ubuntu 22.04/24.04, por SSH)

```bash
ssh root@IP_DEL_VPS

apt update && apt -y upgrade
apt -y install nginx mysql-server git unzip curl software-properties-common
add-apt-repository -y ppa:ondrej/php && apt update
apt -y install php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
               php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl php8.3-sqlite3
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

### Base de datos

Elige una contraseña propia. No la guardes en el repositorio.

```bash
mysql -e "CREATE DATABASE peaje CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER 'peaje'@'localhost' IDENTIFIED BY 'CONTRASENA_SEGURA';"
mysql -e "GRANT ALL ON peaje.* TO 'peaje'@'localhost'; FLUSH PRIVILEGES;"
```

### Código

```bash
mkdir -p /var/www && cd /var/www
git clone https://github.com/TU_USUARIO/peaje.git peaje
cd /var/www/peaje/placas-web
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
nano .env      # ver valores abajo
```

Valores de producción en `/var/www/peaje/placas-web/.env`. Los que no aparecen se dejan como están en `.env.example`.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://peaje.TU-DOMINIO

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=peaje
DB_USERNAME=peaje
DB_PASSWORD=CONTRASENA_SEGURA

GOOGLE_CLIENT_ID=...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://peaje.TU-DOMINIO/auth/google/callback

INFERENCE_URL=https://xxxx.trycloudflare.com     # se completa en el paso 6
INFERENCE_API_TOKEN=EL_MISMO_TOKEN_QUE_EN_COLAB

DEMO_LOGIN=false     # la rúbrica exige acceso solo con Google
```

Cada clave debe aparecer **una sola vez**: si se repite, Laravel usa la primera.

```bash
php artisan migrate --seed --force      # 6 tablas, 20 vehículos, 3 casetas, tarifas y el admin docente
php artisan storage:link                # imágenes de evidencia en /storage/evidencias
mkdir -p public/miniaturas
chown -R www-data:www-data storage bootstrap/cache public/miniaturas
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Para agregar tu propia cuenta de Google a la lista blanca:

```bash
php artisan tinker --execute="App\Models\User::updateOrCreate(['email'=>'TU_CORREO@gmail.com'],['name'=>'Tu Nombre','rol'=>'admin','activo'=>true]);"
```

Para el video necesitas una cuenta **no autorizada**: cualquier Gmail que no esté en la tabla `users`.

---

## 4. Nginx + HTTPS

`/etc/nginx/sites-available/peaje`:

```nginx
server {
    listen 80;
    server_name peaje.TU-DOMINIO;
    root /var/www/peaje/placas-web/public;
    index index.php;
    client_max_body_size 20M;          # fotos de la app

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Panel en tiempo real (SSE): sin buffer para que los eventos lleguen al instante
    location = /pasos/stream {
        include snippets/fastcgi-php.conf;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_buffering off;
        fastcgi_read_timeout 60s;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120s;      # la inferencia en Colab puede tardar
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
ln -s /etc/nginx/sites-available/peaje /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# Cada panel abierto ocupa un proceso PHP mientras dura el SSE: sube el límite
sed -i 's/^pm.max_children = .*/pm.max_children = 20/' /etc/php/8.3/fpm/pool.d/www.conf
systemctl restart php8.3-fpm

# HTTPS gratis
apt -y install certbot python3-certbot-nginx
certbot --nginx -d peaje.TU-DOMINIO --redirect -m TU_CORREO --agree-tos -n

ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw --force enable
```

Abre `https://peaje.TU-DOMINIO/login`. Debe aparecer **solo** el botón de Google.

---

## 5. Google Cloud Console (OAuth)

En **APIs y servicios → Credenciales** del proyecto del `GOOGLE_CLIENT_ID`:

1. **Cliente OAuth "Aplicación web"** (es el que usa Laravel):
   - Orígenes autorizados: `https://peaje.TU-DOMINIO`
   - URI de redirección autorizada: `https://peaje.TU-DOMINIO/auth/google/callback`
2. **Cliente OAuth "Android"** (es el que usa la app):
   - Nombre del paquete: `com.example.yolo_native_app`
   - SHA-1 de la clave con la que se firma el APK. Para la clave debug, en la PC:
     ```powershell
     keytool -list -v -keystore "$env:USERPROFILE\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android
     ```
3. **Pantalla de consentimiento**: si está en modo *Prueba*, agrega como usuarios de prueba tu correo, el del docente (`doc.alvaro.pinto.ma@unifranz.edu.bo`) y la cuenta no autorizada que usarás en el video.

La app envía el `idToken` a `POST /api/v1/auth/google`, y Laravel lo valida con Google y con la lista blanca.

---

## 6. Notebooks en Colab (entrenamiento + inferencia)

Todos los notebooks corren en Colab y comparten la carpeta de Drive `MyDrive/peaje_modelos/`:

| Orden | Notebook | Produce |
|---|---|---|
| 1 | `entrenamiento_m2_segmentacion_vehiculos_roboflow.ipynb` | `m2_best.pt` + `evidencias_m2/` (métricas) |
| 2 | `keypoints_llantas_yolo_detectron2_v3.ipynb` (secciones 8.x) | `llantas_best.pt` + `evidencias_m3/` |
| 3 | `servidor_inferencia_peaje_colab.ipynb` | API pública `INFERENCE_URL` (se deja corriendo) |

Los notebooks 1 y 2 se ejecutan **una sola vez** y piden workspace, proyecto y versión de Roboflow, y la API key de forma oculta. El 3 se ejecuta cada vez que se necesita la API.

1. Abre `Notebooks/servidor_inferencia_peaje_colab.ipynb` en Colab, en **Entorno → GPU (T4)**.
2. Ejecuta las celdas en orden. Los archivos se toman de `MyDrive/peaje_modelos/`. La primera vez pide subir:
   - `inferencia/servidor_unificado.py`
   - `placas-web/python/placas/modelo/yolo-v9-t-384-license-plates-end2end.onnx`
   - Después de subirlos, quedan guardados en Drive. `m2_best.pt` y `llantas_best.pt` ya están ahí si corriste los notebooks 1 y 2.
3. Escribe el token cuando lo pida. Es el mismo valor que va en `INFERENCE_API_TOKEN` del VPS.
4. Copia el `INFERENCE_URL=https://xxxx.trycloudflare.com` que imprime y pégalo en el VPS:
   ```bash
   cd /var/www/peaje/placas-web
   nano .env                 # INFERENCE_URL=... (sin /predict)
   php artisan config:cache
   ```
5. Revisa `https://peaje.TU-DOMINIO/estado`: debe indicar que la inferencia responde.

Si Colab se reinicia, la URL cambia: repite el paso 4. Mantén la pestaña de Colab abierta durante la demo, porque el video debe mostrar Colab en ejecución.

---

## 7. App móvil (compilar en una PC con Flutter)

```powershell
Set-Location "C:\laragon\www\Nueva carpeta\IAM_yolo_native_app-main"
# android/local.properties: corrige flutter.sdk y sdk.dir con las rutas reales de esa PC
flutter pub get
flutter build apk --release `
  --dart-define=API_BASE_URL=https://peaje.TU-DOMINIO `
  --dart-define=GOOGLE_WEB_CLIENT_ID=EL_CLIENT_ID_WEB.apps.googleusercontent.com
```

El APK queda en `build/app/outputs/flutter-apk/app-release.apk`. Instálalo con `adb install -r` o copiándolo al teléfono. Ya no hace falta `adb reverse`: la app habla con el VPS por HTTPS.

---

## 8. Lista de verificación antes de grabar

- [ ] `https://peaje.TU-DOMINIO/login` muestra solo Google, y una cuenta fuera de la lista ve "acceso denegado".
- [ ] La app entra con Google, y la cuenta no autorizada es rechazada.
- [ ] `/estado` muestra la inferencia activa (URL de Colab).
- [ ] En Modo Caseta, la app muestra placa, categoría, ejes y tarifa.
- [ ] `/dashboard` abierto en paralelo muestra el paso sin recargar la página.
- [ ] Una placa no registrada genera una alerta en tiempo real.
- [ ] `/chat` responde los 4 estados: placa con paso, placa registrada sin paso, placa detectada no registrada y placa inexistente. Hay al menos una consulta por texto y una por imagen.
- [ ] Ninguna URL usa `localhost`, `127.0.0.1` ni una IP privada.
