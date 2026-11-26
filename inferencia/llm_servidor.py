# © 2026 Matías Darío Mercier Rengel — Chatbot (Qwen) del Sistema Inteligente de Control de Peaje. Todos los derechos reservados.
"""
Servidor del LLM del agente conversacional (Qwen vía Ollama) para RunPod.

Solo expone el chatbot: la inferencia de placas/vehículos sigue en Colab (INFERENCE_URL).
Ollama escucha en 127.0.0.1:11434; desde Internet se accede por este servidor, protegido con X-API-Token.

    LLM_API_TOKEN=... uvicorn llm_servidor:app --host 0.0.0.0 --port 8000
"""
import hmac
import json
import os
import time
import urllib.request
from typing import Any, Dict, Optional

from fastapi import FastAPI, Header, HTTPException

OLLAMA_URL = os.environ.get("OLLAMA_URL", "http://127.0.0.1:11434")
LLM_MODELO = os.environ.get("LLM_MODELO", "qwen2.5:7b")
LLM_API_TOKEN = os.environ.get("LLM_API_TOKEN", "")

app = FastAPI(title="LLM del agente del peaje (Qwen)", version="1.0.0")


def _ollama(ruta: str, cuerpo: Optional[dict] = None, timeout: int = 120) -> dict:
    datos = None if cuerpo is None else json.dumps(cuerpo).encode("utf-8")
    req = urllib.request.Request(
        OLLAMA_URL.rstrip("/") + ruta, data=datos,
        headers={"Content-Type": "application/json"}, method="POST" if datos else "GET",
    )
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return json.loads(r.read().decode("utf-8"))


def _verificar(token: Optional[str]) -> None:
    if not LLM_API_TOKEN or not token or not hmac.compare_digest(token, LLM_API_TOKEN):
        raise HTTPException(status_code=401, detail="Token inválido")


@app.get("/")
def raiz():
    try:
        modelos = [m.get("name") for m in _ollama("/api/tags", timeout=5).get("models", [])]
        listo = any(m.startswith(LLM_MODELO) for m in modelos)
    except Exception:
        modelos, listo = [], False
    return {"estado": "activo", "servicio": "llm", "autor": "Matías Darío Mercier Rengel", "modelo": LLM_MODELO, "modelo_disponible": listo}


@app.get("/llm/estado")
def llm_estado(x_api_token: Optional[str] = Header(default=None)):
    _verificar(x_api_token)
    return raiz()


@app.post("/llm/chat")
def llm_chat(cuerpo: Dict[str, Any], x_api_token: Optional[str] = Header(default=None)):
    """Conversación con herramientas (function calling) en el formato /api/chat de Ollama."""
    _verificar(x_api_token)
    t0 = time.perf_counter()
    try:
        resp = _ollama("/api/chat", {
            "model": cuerpo.get("model") or LLM_MODELO,
            "messages": cuerpo.get("messages", []),
            "tools": cuerpo.get("tools", []),
            "stream": False,
            "options": {"temperature": 0.1},
        })
    except Exception as e:
        raise HTTPException(status_code=503, detail=f"LLM no disponible: {e}")
    resp["latencia_ms"] = round((time.perf_counter() - t0) * 1000, 2)
    return resp
