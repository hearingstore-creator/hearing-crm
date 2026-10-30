@echo off
rem Windows: double-click to generate the hearing test digit voices (needs Python + internet).
cd /d "%~dp0"
python -m pip install --quiet edge-tts
python make_digits_tts.py %*
pause
