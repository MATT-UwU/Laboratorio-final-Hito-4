<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

// Los tres entornos usan el mismo detector y un lector distinto.
return [
    'venv' => [
        'nombre' => 'venv: fast-plate-ocr',
        'python' => env('PY_PLACAS_VENV'),
        'script' => base_path('python/placas/placas_fastocr.py'),
    ],
    'conda' => [
        'nombre' => 'conda: Tesseract',
        'python' => env('PY_PLACAS_CONDA'),
        'script' => base_path('python/placas/placas_tesseract.py'),
    ],
    'mamba' => [
        'nombre' => 'mamba: EasyOCR',
        'python' => env('PY_PLACAS_MAMBA'),
        'script' => base_path('python/placas/placas_easyocr.py'),
    ],
];