"""
Script de diagnóstico de entorno para la nube (RunPod / Google Colab).
Requisito para bonificación de +5 puntos:
"salida de un script de diagnóstico (versión de Python, torch, CUDA, ultralytics)
ejecutado en cada entorno de la nube, incluida en la carpeta"
"""
import sys
import platform

print("=" * 60)
print("🔍 DIAGNÓSTICO DEL ENTORNO DE INFERENCIA EN LA NUBE")
print("=" * 60)

print(f"Sistema Operativo: {platform.system()} {platform.release()} ({platform.machine()})")
print(f"Python Versión   : {sys.version.split()[0]}")
print(f"Python Ejecutable: {sys.executable}")

# PyTorch & CUDA
try:
    import torch
    print(f"PyTorch Versión  : {torch.__version__}")
    print(f"CUDA Disponible  : {torch.cuda.is_available()}")
    if torch.cuda.is_available():
        print(f"Dispositivo CUDA : {torch.cuda.get_device_name(0)}")
        print(f"Capacidad CUDA   : {torch.cuda.get_device_capability(0)}")
        print(f"VRAM Total (GB)  : {torch.cuda.get_device_properties(0).total_memory / (1024**3):.2f}")
    else:
        print("Dispositivo CUDA : N/A (Ejecutando en CPU)")
except ImportError:
    print("PyTorch          : NO INSTALADO")

# Ultralytics
try:
    import ultralytics
    print(f"Ultralytics      : {ultralytics.__version__}")
except ImportError:
    print("Ultralytics      : NO INSTALADO")

# OpenCV
try:
    import cv2
    print(f"OpenCV           : {cv2.__version__}")
except ImportError:
    print("OpenCV           : NO INSTALADO")

# ONNX Runtime
try:
    import onnxruntime
    print(f"ONNX Runtime     : {onnxruntime.__version__}")
    print(f"Providers ONNX   : {onnxruntime.get_available_providers()}")
except ImportError:
    print("ONNX Runtime     : NO INSTALADO")

print("=" * 60)
print("✓ Diagnóstico completado exitosamente.")
print("=" * 60)
