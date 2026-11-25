import os
import sys
import time
import hmac
from typing import Optional, Dict, Any, List
import cv2
import numpy as np

# Verificación de librerías
try:
    import torch
    CUDA_DISPONIBLE = torch.cuda.is_available()
    DISPOSITIVO = "cuda" if CUDA_DISPONIBLE else "cpu"
except ImportError:
    torch = None
    CUDA_DISPONIBLE = False
    DISPOSITIVO = "cpu"

try:
    from ultralytics import YOLO
    ULTRALYTICS_OK = True
except ImportError:
    YOLO = None
    ULTRALYTICS_OK = False

# Importar OCR
try:
    import pytesseract
    TESSERACT_OK = True
except ImportError:
    pytesseract = None
    TESSERACT_OK = False

try:
    import easyocr
    EASYOCR_READER = easyocr.Reader(['es', 'en'], gpu=CUDA_DISPONIBLE)
except Exception:
    EASYOCR_READER = None

from fastapi import FastAPI, File, UploadFile, Query, Header, HTTPException
from fastapi.responses import JSONResponse
import uvicorn

app = FastAPI(
    title="Endpoint Unificado de Inferencia - Control de Peaje",
    description="Integra M1 (Placas + OCR), M2 (Segmentación de Categoría) y M3 (Keypoints de Ruedas / Ejes)",
    version="1.0.0"
)

# ─── CARGA DE MODELOS ─────────────────────────────────────────────────────────

MODELO_M1_PATH = os.environ.get("MODELO_M1_PATH", "modelo/yolo-v9-t-384-license-plates-end2end.onnx")
MODELO_M2_PATH = os.environ.get("MODELO_M2_PATH", "yolo11n-seg.pt")   # o peso entrenado M2
MODELO_M3_PATH = os.environ.get("MODELO_M3_PATH", "")  # pesos YOLO-pose entrenados con llantas
# Bonificación "pasajeros": segmentación de instancias de personas (clase COCO 0 "person").
MODELO_PASAJEROS_PATH = os.environ.get("MODELO_PASAJEROS_PATH", "yolo11n-seg.pt")
INFERENCE_API_TOKEN = os.environ.get("INFERENCE_API_TOKEN", "")

m1_session = None
modelo_m2 = None
modelo_m3 = None
modelo_pasajeros = None

def inicializar_modelos():
    global m1_session, modelo_m2, modelo_m3, modelo_pasajeros

    # M1: ONNX Runtime
    if os.path.exists(MODELO_M1_PATH):
        try:
            import onnxruntime as ort
            providers = ['CUDAExecutionProvider', 'CPUExecutionProvider'] if CUDA_DISPONIBLE else ['CPUExecutionProvider']
            m1_session = ort.InferenceSession(MODELO_M1_PATH, providers=providers)
            print(f"✓ M1 cargado desde {MODELO_M1_PATH}")
        except Exception as e:
            print(f"⚠️ Error cargando M1 ONNX: {e}")

    # M2: Ultralytics YOLO-seg
    if ULTRALYTICS_OK:
        try:
            modelo_m2 = YOLO(MODELO_M2_PATH)
            print(f"✓ M2 cargado ({MODELO_M2_PATH}) en {DISPOSITIVO}")
        except Exception as e:
            print(f"⚠️ Error cargando M2 YOLO: {e}")

        # M3 debe ser un modelo entrenado con keypoints de llantas, no el YOLO-pose humano de COCO.
        if MODELO_M3_PATH and os.path.exists(MODELO_M3_PATH):
            try:
                modelo_m3 = YOLO(MODELO_M3_PATH)
                print(f"✓ M3 de llantas cargado ({MODELO_M3_PATH}) en {DISPOSITIVO}")
            except Exception as e:
                print(f"⚠️ Error cargando M3 de llantas: {e}")
        else:
            print("⚠️ M3 de llantas no configurado; se usará la estimación por categoría.")

        # Pasajeros: YOLO11-seg de COCO (solo clase "person"); se descarga solo la primera vez.
        try:
            modelo_pasajeros = YOLO(MODELO_PASAJEROS_PATH)
            print(f"✓ Modelo de pasajeros cargado ({MODELO_PASAJEROS_PATH}) en {DISPOSITIVO}")
        except Exception as e:
            print(f"⚠️ Error cargando el modelo de pasajeros: {e}")

@app.on_event("startup")
def startup_event():
    inicializar_modelos()

# ─── FUNCIONES DE INFERENCIA ──────────────────────────────────────────────────

def inferir_m1_placa(img_bgr: np.ndarray, umbral_conf: float = 0.35) -> Dict[str, Any]:
    """Detecta la placa y extrae el texto mediante OCR."""
    placa_detectada = ""
    conf_ocr = 0.0
    caja = None

    # Si hay modelo ONNX M1
    if m1_session is not None:
        try:
            # Mismo preprocesamiento que placas-web/python/placas/detector.py (letterbox 384, relleno 114).
            LADO = 384
            alto_orig, ancho_orig = img_bgr.shape[:2]
            escala = min(LADO / alto_orig, LADO / ancho_orig)
            nuevo_ancho, nuevo_alto = round(ancho_orig * escala), round(alto_orig * escala)
            margen_x, margen_y = (LADO - nuevo_ancho) / 2, (LADO - nuevo_alto) / 2
            lienzo = cv2.resize(img_bgr, (nuevo_ancho, nuevo_alto), interpolation=cv2.INTER_LINEAR)
            lienzo = cv2.copyMakeBorder(
                lienzo, round(margen_y - 0.1), round(margen_y + 0.1),
                round(margen_x - 0.1), round(margen_x + 0.1),
                cv2.BORDER_CONSTANT, value=(114, 114, 114),
            )
            blob = (lienzo[:, :, ::-1].transpose(2, 0, 1) / 255.0).astype(np.float32)[None]

            input_name = m1_session.get_inputs()[0].name
            salidas = m1_session.run(None, {input_name: blob})[0]

            # Modelo end2end: cada fila es [batch, x1, y1, x2, y2, clase, confianza].
            mejor_conf = 0.0
            mejor_box = None
            for _, bx1, by1, bx2, by2, _, conf in salidas:
                conf = float(conf)
                if conf >= umbral_conf and conf > mejor_conf:
                    mejor_conf = conf
                    x1 = int((bx1 - margen_x) / escala)
                    x2 = int((bx2 - margen_x) / escala)
                    y1 = int((by1 - margen_y) / escala)
                    y2 = int((by2 - margen_y) / escala)
                    mejor_box = (max(0, x1), max(0, y1), min(ancho_orig, x2), min(alto_orig, y2))

            if mejor_box:
                caja = mejor_box
                x1, y1, x2, y2 = mejor_box
                recorte = img_bgr[y1:y2, x1:x2]

                # Aplicar OCR (EasyOCR preferido o Tesseract de respaldo)
                if EASYOCR_READER is not None:
                    # Ampliar el recorte mejora la lectura en frames de video (placas pequeñas).
                    recorte_ocr = cv2.resize(recorte, (0, 0), fx=2, fy=2, interpolation=cv2.INTER_CUBIC)
                    res_ocr = EASYOCR_READER.readtext(
                        recorte_ocr,
                        allowlist='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
                    )
                    if res_ocr:
                        # Quedarse con los textos grandes (los caracteres de la placa) y descartar
                        # los pequeños del marco ("BOLIVIA", nombre del estado, etc.).
                        def alto(r):
                            ys = [p[1] for p in r[0]]
                            return max(ys) - min(ys)
                        alto_max = max(alto(r) for r in res_ocr)
                        grandes = [r for r in res_ocr if alto(r) >= 0.55 * alto_max]
                        # Orden de lectura: de arriba abajo y de izquierda a derecha.
                        grandes.sort(key=lambda r: (round(min(p[1] for p in r[0]) / max(alto_max, 1)),
                                                    min(p[0] for p in r[0])))
                        placa_detectada = "".join(r[1] for r in grandes)
                        conf_ocr = float(sum(r[2] for r in grandes) / len(grandes))
                elif TESSERACT_OK and pytesseract is not None:
                    gris = cv2.cvtColor(recorte, cv2.COLOR_BGR2GRAY)
                    gris = cv2.resize(gris, (0, 0), fx=2, fy=2)
                    _, binarizada = cv2.threshold(gris, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
                    texto = pytesseract.image_to_string(binarizada, config='--psm 7 -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789')
                    placa_detectada = texto.strip()
                    conf_ocr = mejor_conf
        except Exception as e:
            print(f"Error inferencia M1: {e}")

    # Normalización básica: mayúsculas, alfanumérico
    placa_limpia = "".join(c for c in placa_detectada.upper() if c.isalnum())
    return {
        "texto": placa_limpia,
        "confianza": round(conf_ocr, 3),
        "box": caja
    }

def inferir_m2_categoria(img_bgr: np.ndarray) -> Dict[str, Any]:
    """Segmenta el vehículo y devuelve la categoría (moto, auto, minibus, camion, bus)."""
    # Mapeo de clases COCO o de dataset propio
    MAPA_CATEGORIAS = {
        "car": "auto",
        "motorcycle": "moto",
        "bus": "bus",
        "truck": "camion",
        "minibus": "minibus",
        "van": "minibus",
        "auto": "auto",
        "auto particular": "auto",
        "automovil": "auto",
        "automóvil": "auto",
        "moto": "moto",
        "motocicleta": "moto",
        "camion": "camion",
        "camión": "camion",
        "minibús": "minibus"
    }

    if modelo_m2 is None:
        return {"categoria": "auto", "confianza": 0.8}

    try:
        res = modelo_m2.predict(img_bgr, conf=0.25, verbose=False)[0]
        if len(res.boxes) > 0:
            # Tomar el objeto de vehículo con mayor área o mayor confianza
            mejor_cat = "auto"
            mejor_conf = 0.0
            mejor_box = None
            for box in res.boxes:
                cls_id = int(box.cls[0])
                cls_name = res.names.get(cls_id, "").lower()
                conf = float(box.conf[0])
                cat_mapeada = MAPA_CATEGORIAS.get(cls_name)
                if cat_mapeada and conf > mejor_conf:
                    mejor_conf = conf
                    mejor_cat = cat_mapeada
                    mejor_box = [float(value) for value in box.xyxy[0].tolist()]
            area_mascara = None
            if mejor_box is not None:
                alto, ancho = img_bgr.shape[:2]
                x1, y1, x2, y2 = mejor_box
                area_mascara = round(max(0.0, (x2 - x1) * (y2 - y1)) / float(ancho * alto), 3)
            return {"categoria": mejor_cat, "confianza": round(mejor_conf, 3), "box": mejor_box, "area_mascara": area_mascara}
    except Exception as e:
        print(f"Error inferencia M2: {e}")

    return {"categoria": "auto", "confianza": 0.5, "box": None}

def contar_pasajeros(
    img_bgr: np.ndarray,
    caja_vehiculo: Optional[List[float]] = None,
    umbral_conf: float = 0.35,
) -> Dict[str, Any]:
    """
    Cuenta las personas visibles DENTRO del vehículo con segmentación de instancias.
    Una persona cuenta como pasajero si al menos el 60 % de su máscara cae dentro de la caja del vehículo.
    No hay reconocimiento facial ni se guardan recortes: solo se devuelve el conteo y las cajas.
    """
    if modelo_pasajeros is None:
        return {"pasajeros": None, "metodo": "no_disponible"}

    try:
        res = modelo_pasajeros.predict(img_bgr, classes=[0], conf=umbral_conf, verbose=False)[0]
        if res.boxes is None or len(res.boxes) == 0:
            return {"pasajeros": 0, "metodo": "segmentacion", "personas_detectadas": 0, "cajas": []}

        alto, ancho = img_bgr.shape[:2]
        cajas = []
        pasajeros = 0
        for i, box in enumerate(res.boxes):
            x1, y1, x2, y2 = [float(v) for v in box.xyxy[0].tolist()]
            dentro = False  # sin vehículo detectado no hay pasajeros
            if caja_vehiculo:
                vx1, vy1, vx2, vy2 = caja_vehiculo
                if res.masks is not None and i < len(res.masks.xy) and len(res.masks.xy[i]) >= 3:
                    # Polígono de la máscara en coordenadas de la imagen original.
                    mascara = np.zeros((alto, ancho), dtype=np.uint8)
                    cv2.fillPoly(mascara, [res.masks.xy[i].astype(np.int32)], 1)
                    sub = mascara[max(0, int(vy1)):int(vy2), max(0, int(vx1)):int(vx2)]
                    total = int(mascara.sum())
                    dentro = total > 0 and (int(sub.sum()) / total) >= 0.6
                else:
                    cx, cy = (x1 + x2) / 2, (y1 + y2) / 2
                    dentro = vx1 <= cx <= vx2 and vy1 <= cy <= vy2
            if dentro:
                pasajeros += 1
                cajas.append([round(x1, 1), round(y1, 1), round(x2, 1), round(y2, 1)])

        return {
            "pasajeros": pasajeros,
            "metodo": "segmentacion",
            "personas_detectadas": len(res.boxes),
            "cajas": cajas,
        }
    except Exception as e:
        print(f"Error contando pasajeros: {e}")
        return {"pasajeros": None, "metodo": "error"}


def inferir_m3_ejes(
    img_bgr: np.ndarray,
    categoria: str,
    caja_vehiculo: Optional[List[float]] = None,
) -> Dict[str, Any]:
    """Cuenta ejes con keypoints de llantas; requiere vista lateral y caja M2."""
    if categoria == "moto":
        return {"ejes": 2, "ruedas_detectadas": 2, "metodo": "categoria"}

    if modelo_m3 is None:
        ejes_defecto = 3 if categoria in ["camion", "bus"] else 2
        return {"ejes": ejes_defecto, "ruedas_detectadas": 0, "metodo": "estimacion"}

    if not caja_vehiculo:
        ejes_defecto = 3 if categoria in ["camion", "bus"] else 2
        return {"ejes": ejes_defecto, "ruedas_detectadas": 0, "metodo": "sin_caja_vehiculo"}

    try:
        res = modelo_m3.predict(img_bgr, conf=0.2, verbose=False)[0]
        if res.keypoints is None or len(res.boxes) == 0:
            raise ValueError("M3 no detectó keypoints de llantas")

        x1, y1, x2, y2 = caja_vehiculo
        ancho = max(1.0, x2 - x1)
        alto = max(1.0, y2 - y1)
        puntos = res.keypoints.xy.cpu().numpy()
        cajas = res.boxes.xyxy.cpu().numpy()
        llantas = []

        for caja, keypoints in zip(cajas, puntos):
            if len(keypoints) < 3:
                continue
            cx, cy = keypoints[0][:2]
            if not (x1 - 0.05 * ancho <= cx <= x2 + 0.05 * ancho
                    and y1 + 0.35 * alto <= cy <= y2 + 0.10 * alto):
                continue
            alto_visible = max(1.0, float(keypoints[1][1] - keypoints[2][1]))
            llantas.append({
                "box": caja.tolist(),
                "keypoint": [round(float(cx), 1), round(float(cy), 1)],
                "centro_x": float(cx),
                "alto_visible": alto_visible,
            })

        if not llantas:
            raise ValueError("No hay llantas asociadas al vehículo")

        # Quitar cajas duplicadas contenidas dentro de una detección mayor.
        llantas.sort(key=lambda rueda: -(
            (rueda["box"][2] - rueda["box"][0]) * (rueda["box"][3] - rueda["box"][1])
        ))
        unicas = []
        for rueda in llantas:
            rx1, ry1, rx2, ry2 = rueda["box"]
            area_rueda = max(1.0, (rx2 - rx1) * (ry2 - ry1))
            duplicada = False
            for existente in unicas:
                ex1, ey1, ex2, ey2 = existente["box"]
                interseccion = max(0.0, min(rx2, ex2) - max(rx1, ex1)) * max(
                    0.0, min(ry2, ey2) - max(ry1, ey1)
                )
                if interseccion / area_rueda >= 0.7:
                    duplicada = True
                    break
            if not duplicada:
                unicas.append(rueda)

        altura_maxima = max(rueda["alto_visible"] for rueda in unicas)
        cercanas = [
            rueda for rueda in unicas
            if rueda["alto_visible"] >= 0.65 * altura_maxima
        ]
        proporcion = ancho / alto
        if len(cercanas) == 2 and proporcion < 1.6:
            raise ValueError("Vista frontal o posterior: ejes no determinables")

        xs = sorted(rueda["centro_x"] for rueda in cercanas)
        alturas = sorted(rueda["alto_visible"] for rueda in cercanas)
        diametro = alturas[len(alturas) // 2]
        ejes = 1 + sum(
            1 for anterior, siguiente in zip(xs, xs[1:])
            if siguiente - anterior > 0.6 * diametro
        )
        return {
            "ejes": min(6, max(2, ejes)),
            "ruedas_detectadas": len(cercanas),
            "keypoints": [r["keypoint"] for r in sorted(cercanas, key=lambda r: r["centro_x"])],
            "metodo": "keypoints_llantas",
        }
    except Exception as e:
        print(f"Error inferencia M3: {e}")

    ejes_defecto = 3 if categoria in ["camion", "bus"] else 2
    return {"ejes": ejes_defecto, "ruedas_detectadas": 0, "metodo": "estimacion"}

# ─── ENDPOINTS HTTP ───────────────────────────────────────────────────────────

@app.get("/")
def estado():
    return {
        "estado": "activo",
        "dispositivo": DISPOSITIVO,
        "cuda": CUDA_DISPONIBLE,
        "m1_cargado": m1_session is not None,
        "m2_cargado": modelo_m2 is not None,
        "m3_cargado": modelo_m3 is not None,
        "pasajeros_cargado": modelo_pasajeros is not None,
        "ocr_disponible": EASYOCR_READER is not None or TESSERACT_OK
    }

@app.post("/predict")
async def predict(
    file: UploadFile = File(...),
    conf: float = Query(0.35),
    x_api_token: Optional[str] = Header(default=None),
):
    """
    Endpoint único exigido por la sección 4.7 del hito:
    Devuelve JSON con: placa, confianza, categoría, ejes y latencia.
    """
    if not INFERENCE_API_TOKEN:
        raise HTTPException(status_code=503, detail="INFERENCE_API_TOKEN no configurado")
    if not x_api_token or not hmac.compare_digest(x_api_token, INFERENCE_API_TOKEN):
        raise HTTPException(status_code=401, detail="Token inválido")

    t0 = time.perf_counter()
    contenido = await file.read()
    arr = np.frombuffer(contenido, np.uint8)
    img = cv2.imdecode(arr, cv2.IMREAD_COLOR)

    if img is None:
        return JSONResponse(status_code=400, content={"error": "Imagen inválida"})

    # 1. Inferencia M1: Placa + OCR
    res_m1 = inferir_m1_placa(img, umbral_conf=conf)

    # 2. Inferencia M2: Segmentación de Categoría
    res_m2 = inferir_m2_categoria(img)

    # 3. Inferencia M3: Keypoints de Ruedas -> Ejes
    res_m3 = inferir_m3_ejes(
        img,
        categoria=res_m2["categoria"],
        caja_vehiculo=res_m2.get("box"),
    )

    # 4. Bonificación: pasajeros dentro del vehículo (segmentación de personas)
    res_pas = contar_pasajeros(img, caja_vehiculo=res_m2.get("box"))

    latencia_ms = round((time.perf_counter() - t0) * 1000, 2)

    return {
        "placa": res_m1["texto"] or None,
        "confianza": res_m1["confianza"],
        "categoria": res_m2["categoria"],
        "ejes": res_m3["ejes"],
        "pasajeros": res_pas["pasajeros"],
        "latencia_ms": latencia_ms,
        # Formato detallado de la rúbrica (sección 4.7)
        "placa_detalle": {
            "texto": res_m1["texto"] or None,
            "confianza": res_m1["confianza"],
            "bbox": list(res_m1["box"]) if res_m1["box"] else None,
        },
        "vehiculo": {
            "categoria": res_m2["categoria"],
            "confianza": res_m2.get("confianza"),
            "area_mascara": res_m2.get("area_mascara"),
        },
        "keypoints": res_m3.get("keypoints", []),
        "metodo_ejes": res_m3.get("metodo"),
        "detalles": {
            "m1": res_m1,
            "m2": res_m2,
            "m3": res_m3,
            "pasajeros": res_pas,
        }
    }

if __name__ == "__main__":
    puerto = int(os.environ.get("PORT", 8000))
    print(f"Iniciando servidor de inferencia unificado en puerto {puerto}...")
    uvicorn.run(app, host="0.0.0.0", port=puerto)
