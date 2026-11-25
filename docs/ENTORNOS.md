# Gestión de entornos con conda/mamba (bonificación +5)

Requisito: archivos `environment.yml` o `requirements.txt` con versiones fijadas **para cada servicio**, instrucciones en el README para recrear cada entorno y la **salida de un script de diagnóstico** (Python, torch, CUDA, ultralytics) ejecutado **en cada entorno de la nube**.

## Entornos del proyecto

| Servicio | Archivo | Entorno | Dónde corre |
|---|---|---|---|
| Inferencia (M1+M2+M3+pasajeros) | `inferencia/environment.yml` | `peaje-inferencia` | Colab / RunPod |
| Entrenamiento M2 y M3 | `Notebooks/environment-entrenamiento.yml` | `peaje-entrenamiento` | Colab |
| Web (Laravel) | `placas-web/composer.lock` + `Dockerfile` | imagen Docker `peaje-app` | VPS |

Script de diagnóstico: `inferencia/diagnostico.py` (versión de Python, torch, CUDA/GPU, ultralytics, OpenCV, ONNX Runtime).

## Recrear un entorno en cualquier máquina con mamba

```bash
mamba env create -f inferencia/environment.yml
conda activate peaje-inferencia
python inferencia/diagnostico.py
```

## Ejecutarlo en Google Colab con micromamba (evidencia para la carpeta)

Colab no trae conda; **micromamba** se instala en segundos sin reiniciar el runtime. Agrega estas celdas en una pestaña nueva de Colab con **GPU T4** (no hace falta tocar los notebooks del sistema):

**Celda 1 — instalar micromamba**
```bash
!curl -Ls https://micro.mamba.pm/api/micromamba/linux-64/latest | tar -xvj -C /usr/local bin/micromamba > /dev/null
!micromamba --version
```

**Celda 2 — subir los archivos** (`environment.yml`, `environment-entrenamiento.yml` y `diagnostico.py`)
```python
from google.colab import files
files.upload()
```

**Celda 3 — crear el entorno de inferencia y diagnosticar** (5–10 min)
```bash
!micromamba create -y -q -n peaje-inferencia -f environment.yml -r /content/mamba
!micromamba run -r /content/mamba -n peaje-inferencia python diagnostico.py --lock | tee diagnostico_colab_inferencia.txt
!micromamba env export -r /content/mamba -n peaje-inferencia > environment.lock.yml
```

**Celda 4 — crear el entorno de entrenamiento y diagnosticar**
```bash
!micromamba create -y -q -n peaje-entrenamiento -f environment-entrenamiento.yml -r /content/mamba
!micromamba run -r /content/mamba -n peaje-entrenamiento python diagnostico.py | tee diagnostico_colab_entrenamiento.txt
!micromamba env export -r /content/mamba -n peaje-entrenamiento > environment-entrenamiento.lock.yml
```

**Celda 5 — descargar la evidencia**
```python
for f in ['diagnostico_colab_inferencia.txt', 'diagnostico_colab_entrenamiento.txt',
          'environment.lock.yml', 'environment-entrenamiento.lock.yml', 'requirements.lock.txt']:
    files.download(f)
```

Sube esos 5 archivos a la carpeta de evidencias de Drive (y los `.lock.yml` al repositorio). Si usas RunPod, `runpod_start.sh` guarda además `/workspace/diagnostico_runpod.txt`.
