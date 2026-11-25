# Dispositivo de alerta ESP32 (bonificación +5)

El ESP32 consulta cada segundo `GET /api/v1/dispositivo/estado` del VPS (cabecera `X-Device-Token`) y señala:

| Señal | Significado |
|---|---|
| LED verde fijo | El endpoint de inferencia (Colab/RunPod) responde |
| LED azul (integrado, GPIO2) parpadea | Se identificó un vehículo (paso nuevo) |
| LED rojo + 3 pitidos del buzzer | Alerta nueva: placa no registrada o sin saldo |
| LED rojo fijo | Hay alertas sin atender en el panel |

## Materiales
ESP32 DevKit, 1 LED verde, 1 LED rojo, 2 resistencias de 220 Ω, 1 buzzer activo de 5 V/3,3 V, protoboard y cables.

## Conexiones
```
GPIO25 ─ 220Ω ─ LED verde (+)   LED verde (−) ─ GND
GPIO26 ─ 220Ω ─ LED rojo  (+)   LED rojo  (−) ─ GND
GPIO27 ─ buzzer (+)              buzzer (−)    ─ GND
GPIO2  = LED azul integrado
```

## Programar
1. Arduino IDE → Preferencias → URL de placas: `https://espressif.github.io/arduino-esp32/package_esp32_index.json` → Gestor de placas → instalar **esp32 (Espressif)**.
2. Gestor de librerías → instalar **ArduinoJson** (v7).
3. Copia `secrets.example.h` como `secrets.h` y completa WiFi y `DEVICE_TOKEN` (en esta PC ya está creado con el token del VPS; solo falta tu WiFi). `secrets.h` no se sube al repositorio.
4. Placa: *ESP32 Dev Module*, puerto COM del ESP32 → Subir. Abre el Monitor Serie a 115200 baudios.

Al arrancar suena un pitido de prueba; luego se conecta al WiFi (LED azul parpadeando) y empieza a consultar el estado.
