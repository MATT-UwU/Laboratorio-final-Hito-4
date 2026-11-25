@echo off
rem API de placas para la app Flutter. Regla: empieza 2, termina 2, contiene M.
cd /d "%~dp0"
"%LOCALAPPDATA%\anaconda3\envs\placas-conda\python.exe" python\placas\servidor_api.py
