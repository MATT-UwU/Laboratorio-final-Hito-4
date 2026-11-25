# Tarea: dejar funcionando los 3 entornos del laboratorio de placas

Hazlo de principio a fin. Ejecuta tú los comandos, revisa cada resultado y corrige lo que falle. No me pidas que ejecute nada salvo que necesites mi permiso.

## Contexto

- Sistema: Windows. Usuario: `Matias`.
- Proyecto Laravel: `C:\laragon\www\Nueva carpeta\placas-web` (la ruta tiene un espacio, usa siempre comillas).
- Scripts: `python\placas\placas_fastocr.py`, `python\placas\placas_tesseract.py`, `python\placas\placas_easyocr.py`, todos con `python\placas\detector.py`.
- Modelos en `python\placas\modelo\`.
- Imagen de prueba: `img\auto.jpg`.
- Miniconda en `C:\Users\Matias\miniconda3`. mamba 2.10 está instalado en `base`.

| Entorno | Python | Lector | Estado |
|---|---|---|---|
| venv | `C:\Users\Matias\entornos\placas-venv\Scripts\python.exe` | fast-plate-ocr | Funciona. Solo faltaba pasar la ruta de la imagen. |
| conda | `C:\Users\Matias\miniconda3\envs\placas-conda\python.exe` | Tesseract | `import cv2` falla. |
| mamba | `C:\Users\Matias\miniconda3\envs\placas-mamba\python.exe` | EasyOCR | Falta `easyocr`. |

## Problemas conocidos

1. **conda:** `ImportError: DLL load failed while importing cv2: Una directiva de Control de aplicaciones bloqueó este archivo.`
   Windows (Smart App Control / WDAC) bloquea las DLL de OpenCV de conda-forge. El `opencv-python-headless` de pip sí carga, como se ve en el venv.
2. **mamba:** `ModuleNotFoundError: No module named 'easyocr'`. conda-forge no tiene `easyocr`, así que el entorno se creó solo con `python=3.11 onnxruntime opencv pip`. También tiene el OpenCV de conda-forge, que Windows bloquearía igual.
3. `conda remove` falla con `CondaToSNonInteractiveError`: hay que aceptar los Términos de Servicio de `repo.anaconda.com/pkgs/main`, `/r` y `/msys2`, o usar `--override-channels -c conda-forge`.

## Pasos

1. Acepta los ToS si hace falta:
   ```
   conda tos accept --override-channels --channel https://repo.anaconda.com/pkgs/main
   conda tos accept --override-channels --channel https://repo.anaconda.com/pkgs/r
   conda tos accept --override-channels --channel https://repo.anaconda.com/pkgs/msys2
   ```
2. **placas-conda:** quita el OpenCV de conda y pon el de pip.
   ```
   conda remove -n placas-conda --force -y opencv py-opencv libopencv
   "C:\Users\Matias\miniconda3\envs\placas-conda\python.exe" -m pip install opencv-python-headless
   ```
   Si quedan restos de `Lib\site-packages\cv2` que causen conflictos, bórralos y reinstala con `--force-reinstall`.
3. **placas-mamba:** haz lo mismo y luego instala EasyOCR con pip.
   ```
   conda remove -n placas-mamba --force -y opencv py-opencv libopencv
   "C:\Users\Matias\miniconda3\envs\placas-mamba\python.exe" -m pip install easyocr
   ```
4. Comprueba los imports en cada entorno:
   ```
   <python de conda> -c "import cv2, numpy, onnxruntime, pytesseract; print(cv2.__version__)"
   <python de mamba> -c "import cv2, numpy, onnxruntime, easyocr; print('ok')"
   ```
   Si `numpy` u `onnxruntime` dan el mismo error de "Control de aplicaciones", cámbialos igual: `conda remove --force` de ese paquete y luego `pip install` del mismo nombre.
5. Ejecuta las tres pruebas desde `placas-web`:
   ```powershell
   & 'C:\Users\Matias\entornos\placas-venv\Scripts\python.exe' python\placas\placas_fastocr.py 'img\auto.jpg'
   & 'C:\Users\Matias\miniconda3\envs\placas-conda\python.exe' python\placas\placas_tesseract.py 'img\auto.jpg'
   & 'C:\Users\Matias\miniconda3\envs\placas-mamba\python.exe' python\placas\placas_easyocr.py 'img\auto.jpg'
   ```
   La primera ejecución de EasyOCR descarga unos 94 MB de modelos en `python\placas\modelo\easyocr`.
6. Si Windows bloquea `tesseract.exe` del entorno conda, **no desactives Smart App Control tú mismo**: si se apaga, no se puede volver a activar sin reinstalar Windows. Explícame la situación y propón alternativas (por ejemplo, el instalador firmado de Tesseract de UB Mannheim y apuntar `pytesseract.tesseract_cmd` a él).
7. Revisa que el `.env` de Laravel tenga estas rutas entre comillas simples:
   ```
   PY_PLACAS_VENV='C:\Users\Matias\entornos\placas-venv\Scripts\python.exe'
   PY_PLACAS_CONDA='C:\Users\Matias\miniconda3\envs\placas-conda\python.exe'
   PY_PLACAS_MAMBA='C:\Users\Matias\miniconda3\envs\placas-mamba\python.exe'
   ```
   Después ejecuta `php artisan optimize:clear`.

## Restricciones

- No modifiques la lógica de los scripts `.py` salvo que sea imprescindible. Si lo haces, explica por qué.
- No borres ni recrees los entornos sin preguntarme antes.
- No cambies configuraciones de seguridad de Windows.

## Cuando termines

Dame:
- la salida de las tres pruebas (placa leída por cada lector);
- los comandos que cambiaron algo;
- cualquier problema que quedó sin resolver.
