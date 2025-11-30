#include <WiFi.h>
#include <MQTT.h>
#include <ESP32Servo.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Preferences.h>
#include <DHT.h>
#include <DHT_U.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <WebServer.h> // ✅ TAMBAHAN: Library Web Server untuk Config Mode

// ================= WiFi & MQTT =================
WiFiClient net;
MQTTClient client;
WebServer server(80); // ✅ Object Web Server

// Variable status Config Mode
bool inConfigMode = false;

// Credentials (Fallback & Server Info)
const char *mqtt_server = "iotsmarthome.cloud.shiftr.io";
const char *mqtt_username = "iotsmarthome";
const char *mqtt_password = "gxBVaUn5Bvf9yfIm";
const String serial_number = "12345678";

// Variables untuk WiFi dari Preferences
String wifiSSID = "";
String wifiPassword = "";

// Track WiFi status
String lastIP = "";
int lastRSSI = 0;
unsigned long lastWiFiStatusPublish = 0;
const unsigned long WIFI_STATUS_CHECK_INTERVAL = 5000;

// URL Tujuan (Hosting InfinityFree)
const char *serverUrl = "http://koneksipintar.infinityfreeapp.com/api/receive_data.php";

// ================= Servo =================
Servo servo;
const int pinServo = 5;
bool doorStatus = false;
unsigned long doorOpenTime = 0;
bool doorTimerActive = false;
const unsigned long DOOR_OPEN_MS = 5000UL;

// ================= RFID =================
const int pinRFID_SDA = 15;
const int pinRFID_RST = 27;
MFRC522 mfrc522(pinRFID_SDA, pinRFID_RST);

// ================= DHT22 =================
const uint8_t DHTPIN = 4;
const uint8_t DHTTYPE = DHT22;
DHT dht(DHTPIN, DHTTYPE);
unsigned long lastDHTRead = 0;
const unsigned long DHT_INTERVAL = 10000UL;

// ================= Relay & Kipas Mini =================
const int pinRelay = 14;
bool kipasStatus = false;
String kipasMode = "auto";

bool lastKipasStatus = false;
String lastKipasMode = "";
unsigned long lastFanPublish = 0;
const unsigned long FAN_PUBLISH_COOLDOWN = 500;

float tempThresholdOn = 38.0;
float tempThresholdOff = 30.0;

// ================= LCD =================
LiquidCrystal_I2C lcd(0x27, 16, 2);

// ================= Preferences (EEPROM) =================
Preferences preferences;
Preferences wifiPrefs;

const String keyCardCount = "cardCount";
const String keyCardPrefix = "card";
const String keyThresholdOn = "threshOn";
const String keyThresholdOff = "threshOff";
const String keyKipasMode = "kipasMode";

// ================= Timing =================
unsigned long lastReconnectAttempt = 0;
const unsigned long RECONNECT_INTERVAL = 5000UL;

// ================= Function Declarations =================
void messageReceived(String &topic, String &payload);
void checkRFID();
void readDHT();
void openDoor();
void closeDoor();
bool isCardRegistered(const String &cardUID);
void controlFan(bool turnOn);
void autoFan(float temperature);
void saveThresholds();
void loadThresholds();
void publishFanState();
void loadWiFiConfig();
void saveWiFiConfig(const String &ssid, const String &password);
void publishWiFiStatus();
void checkWiFiStatusChange();
void kirimKeDatabase(String type, String dataJson);
void setupAPMode();

// ================= Utility: LCD helper =================
void lcdShow(const String &line1, const String &line2, unsigned long durationMs = 0) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(line1);
  lcd.setCursor(0, 1);
  lcd.print(line2);
  if (durationMs > 0) delay(durationMs);
}

// ================= Web Server Functions (Config Mode) =================
void handleRoot() {
  String html = "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width, initial-scale=1'>";
  html += "<style>body{font-family:sans-serif;text-align:center;padding:20px;background:#f4f4f4;}";
  html += "form{background:#fff;padding:20px;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,0.1);}";
  html += "input{padding:12px;width:90%;margin:10px 0;border:1px solid #ddd;border-radius:4px;}";
  html += "button{padding:12px 20px;background:#007bff;color:white;border:none;border-radius:4px;cursor:pointer;width:100%;font-size:16px;}";
  html += "h1{color:#333;}</style></head><body>";
  html += "<h1>Smarthome Config</h1>";
  html += "<form action='/save' method='POST'>";
  html += "<input type='text' name='ssid' placeholder='Nama WiFi (SSID)' required><br>";
  html += "<input type='password' name='pass' placeholder='Password WiFi' required><br>";
  html += "<button type='submit'>Simpan & Restart</button>";
  html += "</form></body></html>";
  server.send(200, "text/html", html);
}

void handleSave() {
  String newSSID = server.arg("ssid");
  String newPass = server.arg("pass");

  if (newSSID.length() > 0) {
    server.send(200, "text/html", "<h1>Disimpan!</h1><p>Alat akan restart dan mencoba connect...</p>");
    delay(500);
    saveWiFiConfig(newSSID, newPass);
    ESP.restart();
  } else {
    server.send(400, "text/plain", "SSID tidak boleh kosong");
  }
}

void setupAPMode() {
  inConfigMode = true;
  WiFi.mode(WIFI_AP);
  
  // Nama Hotspot saat error
  String apName = "Smarthome-Config"; 
  WiFi.softAP(apName.c_str(), "12345678"); // Password hotspot: 12345678

  IPAddress IP = WiFi.softAPIP();
  Serial.print("⚡ AP Mode Started. Connect to: ");
  Serial.println(apName);
  Serial.print("⚡ Open Browser: http://");
  Serial.println(IP);

  lcdShow("Gagal Connect!", "Mode Config Aktif", 2000);
  lcdShow("WiFi: " + apName, "IP: " + IP.toString(), 0);

  server.on("/", handleRoot);
  server.on("/save", handleSave);
  server.begin();
}

// ================= Setup =================
void setup() {
  Serial.begin(115200);
  delay(200);

  Serial.println("\n\n========================================");
  Serial.println("ESP32 Smart Home - Hybrid System");
  Serial.println("========================================\n");

  // Init Hardware
  servo.attach(pinServo, 500, 2400);
  servo.write(0);
  pinMode(pinRelay, OUTPUT);
  digitalWrite(pinRelay, HIGH);
  SPI.begin();
  mfrc522.PCD_Init();
  dht.begin();
  lcd.init();
  lcd.backlight();
  lcdShow("Smart Home ESP32", "Init System...", 1500);

  // Init Preferences
  preferences.begin("rfid", false);
  loadThresholds();
  loadWiFiConfig();

  // Init MQTT Object
  client.begin(mqtt_server, net);
  client.onMessage(messageReceived);

  // ================= LOGIKA KONEKSI WIFI =================
  WiFi.mode(WIFI_STA); // Mode Station (Client)
  
  if (wifiSSID.length() > 0) {
    Serial.println("Menghubungkan ke WiFi Tersimpan: " + wifiSSID);
    WiFi.begin(wifiSSID.c_str(), wifiPassword.c_str());
    lcdShow("Connecting...", wifiSSID, 0);
    
    // Coba connect selama 15 detik
    int retry = 0;
    while (WiFi.status() != WL_CONNECTED && retry < 30) {
      delay(500);
      Serial.print(".");
      retry++;
    }
  } else {
    Serial.println("Belum ada config WiFi.");
  }

  // Cek Hasil Koneksi
  if (WiFi.status() == WL_CONNECTED) {
    // BERHASIL
    Serial.println("\n✅ WiFi Connected!");
    Serial.println("IP: " + WiFi.localIP().toString());
    lcdShow("WiFi Connected", WiFi.localIP().toString(), 2000);
    inConfigMode = false;
  } else {
    // GAGAL -> Masuk Config Mode
    Serial.println("\n❌ Gagal Connect! Mengaktifkan Mode Config...");
    setupAPMode(); // Jalankan AP Mode
  }
  
  lastReconnectAttempt = 0;
}

// ================= Loop =================
void loop() {
  // ✅ 1. Jika Mode Config Aktif, Fokus Web Server Saja
  if (inConfigMode) {
    server.handleClient();
    delay(5);
    return; // Stop, jangan jalankan fitur lain
  }

  // ✅ 2. Kode Normal (Hanya jalan jika WiFi Connect)
  client.loop();

  // Reconnect logic
  if (!client.connected()) {
    if (millis() - lastReconnectAttempt >= RECONNECT_INTERVAL) {
      lastReconnectAttempt = millis();

      if (WiFi.status() == WL_CONNECTED) {
        String willTopic = "smarthome/status/" + serial_number;
        client.setWill(willTopic.c_str(), "offline", true, 1);

        String clientId = "esp32-" + serial_number;
        if (client.connect(clientId.c_str(), mqtt_username, mqtt_password)) {
          Serial.println("✅ MQTT Connected!");
          client.publish(willTopic.c_str(), "online", true, 1);
          client.subscribe(("smarthome/" + serial_number + "/#").c_str(), 1);
          
          publishFanState();
          publishWiFiStatus();
          lcdShow("System Ready", "Tempelkan Kartu", 1000);
        } else {
          Serial.println("❌ MQTT connect failed");
        }
      } else {
        // Jika WiFi putus di tengah jalan, coba reconnect (bukan masuk config mode dulu)
        Serial.println("⚠️ WiFi Lost. Reconnecting...");
        WiFi.reconnect(); 
      }
    }
  }

  checkWiFiStatusChange();
  checkRFID();
  readDHT();

  // Auto close door
  if (doorTimerActive && (millis() - doorOpenTime >= DOOR_OPEN_MS)) {
    closeDoor();
    doorTimerActive = false;
  }

  delay(10);
}

// ================= Helper Functions =================

void kirimKeDatabase(String type, String dataJson) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/json");

    String payload = "{\"type\":\"" + type + "\", \"data\":" + dataJson + "}";
    Serial.print("🌐 Mengirim ke Web: ");
    Serial.println(payload);

    int httpResponseCode = http.POST(payload);

    if (httpResponseCode > 0) {
      Serial.println("✅ Web Response: " + String(httpResponseCode));
    } else {
      Serial.print("❌ Web Error code: ");
      Serial.println(httpResponseCode);
    }
    http.end();
  } else {
    Serial.println("⚠️ Gagal kirim ke Web (No WiFi)");
  }
}

void loadWiFiConfig() {
  wifiPrefs.begin("wifi", false);
  wifiSSID = wifiPrefs.getString("wifi_ssid", "");
  wifiPassword = wifiPrefs.getString("wifi_pass", "");
  wifiPrefs.end();
}

void saveWiFiConfig(const String &ssid, const String &password) {
  wifiPrefs.begin("wifi", false);
  wifiPrefs.putString("wifi_ssid", ssid);
  wifiPrefs.putString("wifi_pass", password);
  wifiPrefs.end();
  wifiSSID = ssid;
  wifiPassword = password;
}

void publishWiFiStatus() {
  if (WiFi.status() != WL_CONNECTED) return;

  JsonDocument doc;
  doc["status"] = "connected";
  doc["ssid"] = WiFi.SSID();
  doc["ip"] = WiFi.localIP().toString();
  doc["rssi"] = WiFi.RSSI();

  String payload;
  serializeJson(doc, payload);
  client.publish(("smarthome/" + serial_number + "/wifi/status").c_str(), payload.c_str(), false, 0);
  
  lastIP = WiFi.localIP().toString();
  lastRSSI = WiFi.RSSI();
  lastWiFiStatusPublish = millis();
}

void checkWiFiStatusChange() {
  if (millis() - lastWiFiStatusPublish < WIFI_STATUS_CHECK_INTERVAL) return;
  if (WiFi.status() != WL_CONNECTED) return;

  if (WiFi.localIP().toString() != lastIP || abs(WiFi.RSSI() - lastRSSI) > 5) {
    publishWiFiStatus();
  }
}

// ... (Fungsi MQTT Callback dan Sensor logic lainnya sama seperti sebelumnya)

void messageReceived(String &topic, String &payload) {
  Serial.println("📩 MQTT: " + topic + " | " + payload);
  payload.trim();

  // Reset System via MQTT (Opsional)
  if (topic.endsWith("/system/reset")) {
      ESP.restart();
  }

  // --- Logic yang ada sebelumnya ---
  // Servo Manual
  if (topic.endsWith("/servo")) {
    int pos = payload.toInt();
    pos = constrain(pos, 0, 180);
    servo.write(pos);
    doorStatus = (pos > 45);
    lcdShow("Servo: " + String(pos), doorStatus ? "Terbuka" : "Tertutup", 800);
  }

  // Kipas Control
  if (topic.endsWith("/kipas/control") && kipasMode == "manual") {
    controlFan(payload == "on");
  }

  // Kipas Mode
  if (topic.endsWith("/kipas/mode")) {
    kipasMode = payload;
    kipasMode.toLowerCase();
    preferences.putString(keyKipasMode.c_str(), kipasMode);
    publishFanState();
    lcdShow("Mode: " + kipasMode, "", 1000);
  }
  
  // Update Threshold
  if (topic.endsWith("/kipas/threshold")) {
     JsonDocument doc;
     if (!deserializeJson(doc, payload)) {
        if(doc["on"].is<float>()) tempThresholdOn = doc["on"];
        if(doc["off"].is<float>()) tempThresholdOff = doc["off"];
        saveThresholds();
     }
  }
}

// ... (Fungsi Hardware: checkRFID, readDHT, controlFan, dll tetap sama)

void checkRFID() {
  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) return;

  String cardUID = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) cardUID += "0";
    cardUID += String(mfrc522.uid.uidByte[i], HEX);
  }
  cardUID.toUpperCase();
  Serial.println("UID: " + cardUID);

  String status = "denied";
  if (isCardRegistered(cardUID)) {
    status = "granted";
    openDoor();
  } else {
    lcdShow("Akses Ditolak", cardUID, 1500);
  }

  // MQTT
  client.publish(("smarthome/" + serial_number + "/rfid/access").c_str(),
                 ("{\"status\":\"" + status + "\"}").c_str(), true, 1);
  
  // HTTP POST (Log)
  kirimKeDatabase("rfid", "{\"uid\":\"" + cardUID + "\",\"status\":\"" + status + "\"}");

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
}

void readDHT() {
  if (millis() - lastDHTRead >= DHT_INTERVAL) {
    lastDHTRead = millis();
    float h = dht.readHumidity();
    float t = dht.readTemperature();

    if (!isnan(h) && !isnan(t)) {
      // MQTT
      client.publish(("smarthome/" + serial_number + "/dht/temperature").c_str(), String(t, 2).c_str(), true, 1);
      client.publish(("smarthome/" + serial_number + "/dht/humidity").c_str(), String(h, 2).c_str(), true, 1);
      
      // HTTP POST (Log)
      String jsonDHT = "{\"temperature\":" + String(t) + ",\"humidity\":" + String(h) + "}";
      kirimKeDatabase("dht", jsonDHT);

      if (kipasMode == "auto") autoFan(t);
    }
  }
}

void openDoor() {
  servo.write(90);
  doorStatus = true;
  doorOpenTime = millis();
  doorTimerActive = true;
  client.publish(("smarthome/" + serial_number + "/pintu/status").c_str(), "terbuka", true, 1);
  kirimKeDatabase("door", "{\"status\":\"terbuka\"}");
  lcdShow("Pintu Terbuka", "Silahkan Masuk", 1000);
}

void closeDoor() {
  servo.write(0);
  doorStatus = false;
  client.publish(("smarthome/" + serial_number + "/pintu/status").c_str(), "tertutup", true, 1);
  kirimKeDatabase("door", "{\"status\":\"tertutup\"}");
  lcdShow("Pintu Tertutup", "", 1000);
}

void controlFan(bool turnOn) {
  if (kipasStatus == turnOn) return;
  kipasStatus = turnOn;
  digitalWrite(pinRelay, turnOn ? LOW : HIGH);
  publishFanState();
}

void autoFan(float temperature) {
  if (!kipasStatus && temperature >= tempThresholdOn) controlFan(true);
  else if (kipasStatus && temperature <= tempThresholdOff) controlFan(false);
}

void publishFanState() {
  String status = kipasStatus ? "on" : "off";
  String msg = status + "," + kipasMode;
  client.publish(("smarthome/" + serial_number + "/kipas/status").c_str(), msg.c_str(), true, 1);
}

bool isCardRegistered(const String &cardUID) {
  int count = preferences.getInt(keyCardCount.c_str(), 0);
  for (int i = 0; i < count; i++) {
    String key = keyCardPrefix + String(i);
    if (preferences.getString(key.c_str(), "") == cardUID) return true;
  }
  return false;
}

void saveThresholds() {
  preferences.putFloat(keyThresholdOn.c_str(), tempThresholdOn);
  preferences.putFloat(keyThresholdOff.c_str(), tempThresholdOff);
}

void loadThresholds() {
  tempThresholdOn = preferences.getFloat(keyThresholdOn.c_str(), 38.0);
  tempThresholdOff = preferences.getFloat(keyThresholdOff.c_str(), 30.0);
  kipasMode = preferences.getString(keyKipasMode.c_str(), "auto");
}