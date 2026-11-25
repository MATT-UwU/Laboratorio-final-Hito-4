# Inferencia en RunPod (bonificación +5)

El enunciado exige que el endpoint de inferencia corra en **RunPod** durante la demo y en la fecha de revisión, y que el video muestre la consola de RunPod y una petición en vivo. Colab vale para la nota base pero no para esta bonificación.

Costo aproximado: una GPU chica (RTX A4000 / RTX 3090 / A5000) cuesta ~0,2–0,45 USD/h en *Community Cloud*. Un Network Volume de 10 GB cuesta ~0,7 USD/mes. Detén el Pod cuando no lo uses (el volumen conserva los archivos), y déjalo encendido el día de la revisión.

## 1. Crear la cuenta y el Pod (tú, en runpod.io)

1. Crea la cuenta en https://www.runpod.io y carga saldo (mínimo 10 USD).
2. **Storage → Network Volume → New**: 10 GB, en el mismo datacenter donde haya GPUs disponibles. Nombre: `peaje`.
3. **Pods → Deploy**:
   - GPU: RTX A4000 o RTX 3090 (Community Cloud).
   - Template: **RunPod PyTorch 2.x** (trae Python, CUDA y torch).
   - Network Volume: `peaje` (se monta en `/workspace`).
   - **Expose HTTP Ports: `8000`** (además del 8888 de Jupyter).
   - Deploy.
4. Cuando esté *Running*: **Connect → Jupyter Lab** (o Web Terminal).

## 2. Subir los archivos (tú, desde Jupyter Lab del Pod)

Crea estas carpetas y sube los archivos (arrastrar al panel de Jupyter):

```
/workspace/inferencia/   ← servidor_unificado.py, requirements.txt, runpod_start.sh, diagnostico.py  (de C:\laragon\www\Nueva carpeta\inferencia\)
/workspace/modelos/      ← yolo-v9-t-384-license-plates-end2end.onnx   (placas-web\python\placas\modelo\)
                           m2_best.pt y llantas_best.pt                  (de tu Drive: MyDrive/peaje_modelos/)
```

## 3. Arrancar el servidor (tú, en la terminal del Pod)

```bash
cd /workspace/inferencia
export INFERENCE_API_TOKEN='EL_MISMO_TOKEN_QUE_EN_EL_VPS'
bash runpod_start.sh
```

El script instala dependencias, guarda el diagnóstico del entorno en `/workspace/diagnostico_runpod.txt` (evidencia para la bonificación de entornos), descarga el modelo de pasajeros y levanta FastAPI en el puerto 8000.

URL pública: **`https://<POD_ID>-8000.proxy.runpod.net`** (en la consola: Connect → HTTP Service 8000). Pruébala en el navegador: `/` debe devolver `"estado": "activo"` y los modelos cargados.

Para dejarlo corriendo aunque cierres la terminal: `nohup bash runpod_start.sh > /workspace/servidor.log 2>&1 &`.

## 4. Conectar el VPS (yo)

Me pasas la URL `https://<POD_ID>-8000.proxy.runpod.net` y la pongo como `INFERENCE_URL` en `/var/www/peaje/placas-web/.env` + `docker compose restart app`. A diferencia de Colab, la URL **no cambia** mientras el Pod exista (aunque se detenga y vuelva a arrancar).

## 5. Para el video

- Mostrar la consola de RunPod con el Pod *Running* y la GPU.
- Mostrar `servidor.log` recibiendo un `POST /predict` en vivo mientras la app registra un paso.
- Mostrar `/estado` en la web indicando la inferencia activa.

## Versiones fijadas

En el Pod (o en Colab) ejecuta `python diagnostico.py --lock`: escribe `requirements.lock.txt` con las versiones exactas instaladas. Súbelo al repositorio y a la carpeta de evidencias junto con la salida del diagnóstico.
