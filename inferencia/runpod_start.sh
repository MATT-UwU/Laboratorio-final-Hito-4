#!/usr/bin/env bash
# Arranque del servidor de inferencia en un Pod de RunPod (bonificación "Inferencia en RunPod en vivo").
#
# Uso dentro del Pod (terminal web de RunPod):
#   cd /workspace/inferencia
#   export INFERENCE_API_TOKEN='el_mismo_token_que_en_el_VPS'
#   bash runpod_start.sh
#
# Estructura esperada en /workspace (Network Volume o disco del Pod, persiste entre reinicios):
#   /workspace/inferencia/servidor_unificado.py, requirements.txt, runpod_start.sh, diagnostico.py
#   /workspace/modelos/yolo-v9-t-384-license-plates-end2end.onnx   (M1)
#   /workspace/modelos/m2_best.pt                                   (M2)
#   /workspace/modelos/llantas_best.pt                              (M3)
#
# La URL pública queda en: https://<POD_ID>-8000.proxy.runpod.net  (exponer el puerto HTTP 8000 en el Pod)
set -euo pipefail

cd "$(dirname "$0")"

if [ -z "${INFERENCE_API_TOKEN:-}" ]; then
  echo "Falta INFERENCE_API_TOKEN (export INFERENCE_API_TOKEN='...')" >&2
  exit 1
fi

MODELOS="${MODELOS_DIR:-/workspace/modelos}"
export MODELO_M1_PATH="${MODELO_M1_PATH:-$MODELOS/yolo-v9-t-384-license-plates-end2end.onnx}"
export MODELO_M2_PATH="${MODELO_M2_PATH:-$MODELOS/m2_best.pt}"
export MODELO_M3_PATH="${MODELO_M3_PATH:-$MODELOS/llantas_best.pt}"
export MODELO_PASAJEROS_PATH="${MODELO_PASAJEROS_PATH:-$MODELOS/yolo11n-seg.pt}"

for f in "$MODELO_M1_PATH" "$MODELO_M2_PATH" "$MODELO_M3_PATH"; do
  [ -f "$f" ] || echo "AVISO: no existe $f" >&2
done

# Dependencias (versiones fijadas). Si el Pod se reinicia sin volumen, se reinstalan.
apt-get update -qq && apt-get install -y -qq tesseract-ocr libgl1 libglib2.0-0 > /dev/null || true
pip install -q -r requirements.txt

# Evidencia para la bonificación de entornos
python diagnostico.py | tee /workspace/diagnostico_runpod.txt

# El modelo de pasajeros se descarga solo la primera vez y queda en /workspace/modelos
if [ ! -f "$MODELO_PASAJEROS_PATH" ]; then
  python -c "from ultralytics import YOLO; import shutil; m=YOLO('yolo11n-seg.pt'); shutil.copy('yolo11n-seg.pt', '$MODELO_PASAJEROS_PATH')" || true
fi

echo "Servidor en http://0.0.0.0:8000  →  https://${RUNPOD_POD_ID:-<POD_ID>}-8000.proxy.runpod.net"
exec uvicorn servidor_unificado:app --host 0.0.0.0 --port 8000
