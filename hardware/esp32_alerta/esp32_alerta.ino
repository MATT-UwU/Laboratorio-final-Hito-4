// © 2026 Matías Darío Mercier Rengel — Dispositivo de alerta ESP32 del Sistema Inteligente de Control de Peaje. Todos los derechos reservados.
/*
 * Dispositivo de alerta del peaje (bonificación "Conexión de alerta a un dispositivo de hardware").
 *
 * Un ESP32 consulta cada segundo GET /api/v1/dispositivo/estado del VPS (HTTPS) y señala:
 *   - LED verde fijo   : el endpoint de inferencia (Colab/RunPod) responde.
 *   - LED azul (GPIO2) : parpadea con cada vehículo identificado (paso nuevo).
 *   - LED rojo + buzzer: alerta nueva (placa no registrada / sin saldo). El LED rojo queda
 *                        encendido mientras haya alertas pendientes sin atender.
 *
 * Conexiones (con resistencia de 220 Ω en serie con cada LED):
 *   GPIO25 -> LED verde -> GND      GPIO26 -> LED rojo -> GND
 *   GPIO27 -> buzzer activo (+)     buzzer (-) -> GND
 *   GPIO2  -> LED azul integrado de la placa
 *
 * Librerías: WiFi, HTTPClient y WiFiClientSecure (vienen con el core ESP32) y ArduinoJson (v7).
 * Configura la red y el token en secrets.h (copia secrets.example.h). secrets.h NO se sube al repositorio.
 */
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "secrets.h"

const int PIN_VERDE  = 25;
const int PIN_ROJO   = 26;
const int PIN_BUZZER = 27;
const int PIN_AZUL   = 2;

const unsigned long INTERVALO_MS = 1000;

long ultimoPasoId = -1;
long ultimaAlertaId = -1;
unsigned long ultimaConsulta = 0;

void pitido(int veces, int ms) {
  for (int i = 0; i < veces; i++) {
    digitalWrite(PIN_BUZZER, HIGH);
    digitalWrite(PIN_ROJO, HIGH);
    delay(ms);
    digitalWrite(PIN_BUZZER, LOW);
    digitalWrite(PIN_ROJO, LOW);
    delay(ms);
  }
}

void parpadeoAzul() {
  for (int i = 0; i < 3; i++) {
    digitalWrite(PIN_AZUL, HIGH);
    delay(80);
    digitalWrite(PIN_AZUL, LOW);
    delay(80);
  }
}

void conectarWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Conectando a WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    digitalWrite(PIN_AZUL, !digitalRead(PIN_AZUL));
    delay(300);
    Serial.print(".");
  }
  digitalWrite(PIN_AZUL, LOW);
  Serial.printf("\nWiFi OK, IP %s\n", WiFi.localIP().toString().c_str());
}

void setup() {
  Serial.begin(115200);
  pinMode(PIN_VERDE, OUTPUT);
  pinMode(PIN_ROJO, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  pinMode(PIN_AZUL, OUTPUT);
  pitido(1, 100);  // prueba de hardware al arrancar
  conectarWifi();
}

void consultarEstado() {
  WiFiClientSecure cliente;
  cliente.setInsecure();  // demo: no valida el certificado (el tráfico igual va cifrado)

  HTTPClient http;
  http.setTimeout(5000);
  if (!http.begin(cliente, String(API_URL) + "/api/v1/dispositivo/estado")) {
    Serial.println("No se pudo iniciar HTTP");
    return;
  }
  http.addHeader("X-Device-Token", DEVICE_TOKEN);
  http.addHeader("Accept", "application/json");

  int codigo = http.GET();
  if (codigo != 200) {
    Serial.printf("HTTP %d\n", codigo);
    digitalWrite(PIN_VERDE, LOW);
    http.end();
    return;
  }

  JsonDocument doc;
  DeserializationError err = deserializeJson(doc, http.getString());
  http.end();
  if (err) {
    Serial.printf("JSON inválido: %s\n", err.c_str());
    return;
  }

  bool inferenciaOk = doc["inferencia_ok"] | false;
  long pasoId = doc["ultimo_paso_id"] | -1L;
  long alertaId = doc["ultima_alerta_id"] | -1L;
  int pendientes = doc["alertas_pendientes"] | 0;
  const char* placa = doc["ultima_placa"] | "";
  const char* estado = doc["ultimo_paso_estado"] | "";

  // Proceso de inferencia disponible
  digitalWrite(PIN_VERDE, inferenciaOk ? HIGH : LOW);

  // Identificación: nuevo vehículo registrado
  if (ultimoPasoId >= 0 && pasoId > ultimoPasoId) {
    Serial.printf("Paso nuevo #%ld placa %s estado %s\n", pasoId, placa, estado);
    parpadeoAzul();
  }

  // Alerta nueva: placa no registrada o sin saldo
  if (ultimaAlertaId >= 0 && alertaId > ultimaAlertaId) {
    Serial.printf("ALERTA #%ld (%s) placa %s\n", alertaId, (const char*)(doc["ultima_alerta_tipo"] | ""), placa);
    pitido(3, 150);
  }

  // Mientras haya alertas sin atender, el LED rojo queda encendido
  digitalWrite(PIN_ROJO, pendientes > 0 ? HIGH : LOW);

  ultimoPasoId = pasoId;
  ultimaAlertaId = alertaId;
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    digitalWrite(PIN_VERDE, LOW);
    conectarWifi();
  }
  if (millis() - ultimaConsulta >= INTERVALO_MS) {
    ultimaConsulta = millis();
    consultarEstado();
  }
}
