"""API de placas para la app Flutter (reemplaza a la API de YOLO).

Entorno conda: detector YOLOv9 en ONNX (detector.py) + lectura con Tesseract (placas_tesseract.py).
Es la misma lógica de placas-web, pero como servidor HTTP con el mismo contrato que usaba la app:

    GET  /           -> estado
    POST /predict    -> multipart 'file' (JPG) -> {"detections": [...], "inference_ms": ..., "registradas": [...]}
    GET  /registros  -> placas registradas (las que cumplen la regla)

Regla de registro (fila 12, Matias Dario Mercier Rengel): empieza por 2, termina en 2, contiene M.
Se puede cambiar con las variables de entorno REGLA_EMPIEZA, REGLA_TERMINA y REGLA_CONTIENE.

Uso (desde placas-web, con el Python del entorno conda):
    python python\\placas\\servidor_api.py            # puerto 5000
    ngrok http 5000                                   # y se pone la URL en lib/main.dart
"""
import csv
import os
import threading
import time
from datetime import datetime
from pathlib import Path

import cv2
import numpy as np
from flask import Flask, jsonify, request

import placas_tesseract as ocr  # configura pytesseract y reutiliza preparar() / caracteres_principales()
from detector import detectar, limpiar

EMPIEZA = os.environ.get("REGLA_EMPIEZA", "2").upper()
TERMINA = os.environ.get("REGLA_TERMINA", "2").upper()
CONTIENE = os.environ.get("REGLA_CONTIENE", "M").upper()
REGISTRO = Path(os.environ.get("REGISTRO_CSV", Path(__file__).parents[2] / "storage" / "app" / "placas_registradas.csv"))
UMBRAL_DETECCION = float(os.environ.get("UMBRAL_DETECCION", "0.4"))

app = Flask(__name__)
candado = threading.Lock()
registradas = {}  # texto -> fila, para no repetir la misma placa


def cumple_regla(texto):
    return (len(texto) >= 3 and texto.startswith(EMPIEZA) and texto.endswith(TERMINA) and CONTIENE in texto)


def cargar_registro():
    if REGISTRO.is_file():
        with REGISTRO.open(newline="", encoding="utf-8") as f:
            for fila in csv.DictReader(f):
                registradas[fila["placa"]] = fila


def registrar(texto, confianza):
    """Guarda la placa si es nueva. Devuelve True si se registró ahora."""
    with candado:
        if texto in registradas:
            return False
        fila = {"placa": texto, "fecha": datetime.now().isoformat(timespec="seconds"), "confianza": confianza}
        registradas[texto] = fila
        REGISTRO.parent.mkdir(parents=True, exist_ok=True)
        nuevo = not REGISTRO.is_file()
        with REGISTRO.open("a", newline="", encoding="utf-8") as f:
            escritor = csv.DictWriter(f, fieldnames=["placa", "fecha", "confianza"])
            if nuevo:
                escritor.writeheader()
            escritor.writerow(fila)
        return True


@app.get("/")
def estado():
    return jsonify({"estado": "ok", "regla": {"empieza": EMPIEZA, "termina": TERMINA, "contiene": CONTIENE}})


@app.get("/registros")
def registros():
    return jsonify(list(registradas.values()))


@app.post("/predict")
def predict():
    inicio = time.perf_counter()
    archivo = request.files.get("file")
    if archivo is None:
        return jsonify({"error": "falta el campo 'file'"}), 400
    imagen = cv2.imdecode(np.frombuffer(archivo.read(), np.uint8), cv2.IMREAD_COLOR)
    if imagen is None:
        return jsonify({"error": "imagen inválida"}), 400

    detecciones = []
    for x1, y1, x2, y2, confianza in detectar(imagen, float(request.args.get("conf", UMBRAL_DETECCION))):
        datos = ocr.pytesseract.image_to_data(
            ocr.preparar(imagen[y1:y2, x1:x2]), lang="eng", config=ocr.CONFIGURACION,
            output_type=ocr.pytesseract.Output.DICT,
        )
        texto, lectura = ocr.caracteres_principales(datos)
        texto = limpiar(texto)
        cumple = cumple_regla(texto)
        nueva = registrar(texto, None if lectura is None else round(lectura * 100, 1)) if cumple else False
        detecciones.append({
            "class_id": 0,
            "label": texto or "?",
            "score": confianza,
            "box": {"xmin": x1, "ymin": y1, "xmax": x2, "ymax": y2, "width": x2 - x1, "height": y2 - y1},
            "plate": {"texto": texto, "cumple_regla": cumple, "registrada_ahora": nueva},
        })

    return jsonify({
        "detections": detecciones,
        "inference_ms": round((time.perf_counter() - inicio) * 1000, 1),
        "registradas": list(registradas.keys()),
    })


if __name__ == "__main__":
    cargar_registro()
    app.run(host="0.0.0.0", port=int(os.environ.get("PORT", "5000")), threaded=False)
