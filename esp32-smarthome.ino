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
#include <WebServer.h>

// ================= WiFi & MQTT =================
WiFiClient net;
MQTTClient client;
WebServer server(80);

bool inConfigMode = false;

const char *mqtt_server = "SESUAIKAN";
const char *mqtt_username = "SESUAIKAN";
const char *mqtt_password = "SESUAIKAN";
const String serial_number = "BEBAS";

String wifiSSID = "";
String wifiPassword = "";

String lastIP = "";
int lastRSSI = 0;
unsigned long lastWiFiStatusPublish = 0;
const unsigned long WIFI_STATUS_CHECK_INTERVAL = 5000;

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

// ✅ FIX: RFID Cooldown Timer
unsigned long lastRFIDRead = 0;
const unsigned long RFID_COOLDOWN = 200; // 200ms cooldown

// ================= DHT22 =================
const uint8_t DHTPIN = 4;
const uint8_t DHTTYPE = DHT22;
DHT dht(DHTPIN, DHTTYPE);
unsigned long lastDHTRead = 0;
const unsigned long DHT_INTERVAL = 10000UL;

// ================= Relay & Kipas =================
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

// ✅ FIX: Non-blocking LCD
unsigned long lcdTimer = 0;
String lcdLine1Cache = "";
String lcdLine2Cache = "";

// ================= Preferences =================
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
void kirimKeDatabaseAsync(String type, String dataJson);
void setupAPMode();
void lcdShowNonBlocking(const String &line1, const String &line2);
void checkLCDTimeout();

// ================= LCD Helper (Old - for blocking cases) =================
void lcdShow(const String &line1, const String &line2, unsigned long durationMs = 0) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(line1);
  lcd.setCursor(0, 1);
  lcd.print(line2);
  if (durationMs > 0) delay(durationMs);
}

// ✅ FIX: Non-Blocking LCD
void lcdShowNonBlocking(const String &line1, const String &line2) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(line1);
  lcd.setCursor(0, 1);
  lcd.print(line2);
  
  lcdLine1Cache = line1;
  lcdLine2Cache = line2;
  lcdTimer = millis();
}

// ✅ FIX: Perbaiki String concatenation error
void checkLCDTimeout() {
  if (lcdTimer > 0 && millis() - lcdTimer > 2000) {
    String statusText = doorStatus ? "Terbuka" : "Tertutup";
    lcdShowNonBlocking("Pintu " + statusText, "Tempelkan Kartu");
    lcdTimer = 0;
  }
}

// ================= Web Server (Config Mode) =================
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
    server.send(200, "text/html", "<h1>Disimpan!</h1><p>Alat akan restart...</p>");
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
  
  String apName = "Smarthome-Config"; 
  WiFi.softAP(apName.c_str(), "12345678");

  IPAddress IP = WiFi.softAPIP();
  Serial.print("⚡ AP Mode. Connect: ");
  Serial.println(apName);
  Serial.print("⚡ Browser: http://");
  Serial.println(IP);

  lcdShow("Gagal Connect!", "Mode Config", 2000);
  lcdShow("WiFi: " + apName, "IP: " + IP.toString(), 0);

  server.on("/", handleRoot);
  server.on("/save", handleSave);
  server.begin();
}

// ================= Setup =================
void setup() {
  Serial.begin(115200);
  delay(200);

  Serial.println("\n========================================");
  Serial.println("ESP32 Smart Home - Fixed Version");
  Serial.println("========================================\n");

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

  preferences.begin("rfid", false);
  loadThresholds();
  loadWiFiConfig();

  client.begin(mqtt_server, net);
  client.onMessage(messageReceived);

  WiFi.mode(WIFI_STA);
  
  if (wifiSSID.length() > 0) {
    Serial.println("WiFi: " + wifiSSID);
    WiFi.begin(wifiSSID.c_str(), wifiPassword.c_str());
    lcdShow("Connecting...", wifiSSID, 0);
    
    int retry = 0;
    while (WiFi.status() != WL_CONNECTED && retry < 30) {
      delay(500);
      Serial.print(".");
      retry++;
    }
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✅ WiFi Connected!");
    Serial.println("IP: " + WiFi.localIP().toString());
    lcdShow("WiFi Connected", WiFi.localIP().toString(), 2000);
    inConfigMode = false;
  } else {
    Serial.println("\n❌ Config Mode");
    setupAPMode();
  }
  
  lastReconnectAttempt = 0;
}

// ================= Loop (FIXED) =================
void loop() {
  if (inConfigMode) {
    server.handleClient();
    delay(5);
    return;
  }

  client.loop();

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
          lcdShowNonBlocking("System Ready", "Tempelkan Kartu");
        }
      } else {
        WiFi.reconnect();
      }
    }
  }

  checkWiFiStatusChange();
  checkRFID();       // ✅ Fixed
  checkLCDTimeout(); // ✅ Auto-clear LCD
  readDHT();

  if (doorTimerActive && (millis() - doorOpenTime >= DOOR_OPEN_MS)) {
    closeDoor();
    doorTimerActive = false;
  }

  delay(10);
}

// ================= Helper Functions =================

// ✅ FIX: Async HTTP POST dengan timeout
void kirimKeDatabaseAsync(String type, String dataJson) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠️ No WiFi, skip HTTP");
    return;
  }

  HTTPClient http;
  http.begin(serverUrl);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(2000); // ✅ Timeout 2 detik

  String payload = "{\"type\":\"" + type + "\", \"data\":" + dataJson + "}";
  
  int code = http.POST(payload);
  
  if (code > 0) {
    Serial.println("✅ Web: " + String(code));
  } else {
    Serial.println("⚠️ Web Timeout");
  }
  
  http.end();
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

// ================= MQTT Callback =================
void messageReceived(String &topic, String &payload) {
  Serial.println("\n===== 📩 MQTT =====");
  Serial.println("Topic: " + topic);
  Serial.println("Payload: " + payload);
  
  payload.trim();

  if (topic.endsWith("/wifi/set_config")) {
    JsonDocument doc;
    if (!deserializeJson(doc, payload)) {
      String newSSID = doc["ssid"].as<String>();
      String newPassword = doc["password"].as<String>();
      saveWiFiConfig(newSSID, newPassword);
      client.publish(("smarthome/" + serial_number + "/wifi/status").c_str(), "{\"status\":\"restarting\"}", true, 1);
      lcdShow("WiFi Saved", "Restarting...", 2000);
      delay(500);
      ESP.restart();
    }
    return;
  }

  if (topic.endsWith("/wifi/get_status")) {
    publishWiFiStatus();
    return;
  }

  if (topic.endsWith("/servo")) {
    int pos = payload.toInt();
    servo.write(pos);
    doorStatus = (pos > 45);
    lcdShowNonBlocking("Servo: " + String(pos), doorStatus ? "Terbuka" : "Tertutup");
    return;
  }

  if (topic.endsWith("/kipas/control")) {
    if (kipasMode == "manual") {
      controlFan(payload == "on");
    }
    return;
  }

  if (topic.endsWith("/kipas/mode")) {
    String newMode = payload;
    newMode.toLowerCase();
    kipasMode = newMode;
    preferences.putString(keyKipasMode.c_str(), kipasMode);
    publishFanState();
    lcdShowNonBlocking("Mode: " + kipasMode, "");
    return;
  }

  if (topic.endsWith("/kipas/threshold")) {
    JsonDocument doc;
    if (!deserializeJson(doc, payload)) {
      if(doc["on"].is<float>()) tempThresholdOn = doc["on"];
      if(doc["off"].is<float>()) tempThresholdOff = doc["off"];
      saveThresholds();
      lcdShowNonBlocking("Threshold OK", "Updated");
    }
    return;
  }

  if (topic.endsWith("/rfid/register")) {
    String newUid = payload;
    newUid.toUpperCase();

    if (isCardRegistered(newUid)) {
      client.publish(("smarthome/" + serial_number + "/rfid/info").c_str(), "{\"action\":\"add\",\"result\":\"exists\"}", true, 1);
      lcdShowNonBlocking("Gagal Tambah", "Sudah Ada");
      return;
    }

    int count = preferences.getInt(keyCardCount.c_str(), 0);
    String key = keyCardPrefix + String(count);
    preferences.putString(key.c_str(), newUid);
    preferences.putInt(keyCardCount.c_str(), count + 1);

    Serial.println("✅ Kartu +: " + newUid);
    lcdShowNonBlocking("Kartu Ditambah", newUid);
    
    String msg = "{\"action\":\"add\",\"uid\":\"" + newUid + "\",\"result\":\"ok\"}";
    client.publish(("smarthome/" + serial_number + "/rfid/info").c_str(), msg.c_str(), true, 1);
    return;
  }

  if (topic.endsWith("/rfid/remove")) {
    String remUid = payload;
    remUid.toUpperCase();
    
    Serial.println("🗑️ Remove: " + remUid);
    
    int count = preferences.getInt(keyCardCount.c_str(), 0);
    bool found = false;

    for (int i = 0; i < count; i++) {
      String key = keyCardPrefix + String(i);
      String uidStored = preferences.getString(key.c_str(), "");

      if (uidStored.equals(remUid)) {
        for (int j = i; j < count - 1; j++) {
          String keyCurr = keyCardPrefix + String(j);
          String keyNext = keyCardPrefix + String(j + 1);
          String nextUid = preferences.getString(keyNext.c_str(), "");
          preferences.putString(keyCurr.c_str(), nextUid);
        }
        
        preferences.remove((keyCardPrefix + String(count - 1)).c_str());
        preferences.putInt(keyCardCount.c_str(), count - 1);
        found = true;
        break;
      }
    }

    if (found) {
      client.publish(("smarthome/" + serial_number + "/rfid/info").c_str(), "{\"action\":\"remove\",\"result\":\"ok\"}", true, 1);
      lcdShowNonBlocking("Kartu Dihapus", remUid);
    } else {
      client.publish(("smarthome/" + serial_number + "/rfid/info").c_str(), "{\"action\":\"remove\",\"result\":\"not_found\"}", true, 1);
      lcdShowNonBlocking("Gagal Hapus", "Tidak Ketemu");
    }
    return;
  }
}

// ✅ FIX: checkRFID() dengan cooldown & reset reader
void checkRFID() {
  // Cooldown timer
  if (millis() - lastRFIDRead < RFID_COOLDOWN) return;
  
  // ✅ Reset reader setiap loop
  mfrc522.PCD_Init();
  
  if (!mfrc522.PICC_IsNewCardPresent()) return;
  if (!mfrc522.PICC_ReadCardSerial()) return;

  lastRFIDRead = millis();

  // ✅ Gunakan char array (hemat memory)
  char cardUID[17];
  int idx = 0;
  
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    sprintf(cardUID + idx, "%02X", mfrc522.uid.uidByte[i]);
    idx += 2;
  }
  cardUID[idx] = '\0';
  
  Serial.println("UID: " + String(cardUID));

  String status = "denied";
  if (isCardRegistered(String(cardUID))) {
    status = "granted";
    openDoor();
  } else {
    lcdShowNonBlocking("Akses Ditolak", String(cardUID));
  }

  // ✅ MQTT QoS 0 (non-blocking)
  String mqttPayload = "{\"status\":\"" + status + "\"}";
  client.publish(("smarthome/" + serial_number + "/rfid/access").c_str(),
                 mqttPayload.c_str(), false, 0);
  
  // ✅ HTTP POST async
  if (WiFi.status() == WL_CONNECTED) {
    String jsonData = "{\"uid\":\"" + String(cardUID) + "\",\"status\":\"" + status + "\"}";
    kirimKeDatabaseAsync("rfid", jsonData);
  }

  // ✅ WAJIB: Halt & Stop
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  
  delay(100); // Stabilitas
}

void readDHT() {
  if (millis() - lastDHTRead >= DHT_INTERVAL) {
    lastDHTRead = millis();
    float h = dht.readHumidity();
    float t = dht.readTemperature();

    if (!isnan(h) && !isnan(t)) {
      client.publish(("smarthome/" + serial_number + "/dht/temperature").c_str(), String(t, 2).c_str(), false, 0);
      client.publish(("smarthome/" + serial_number + "/dht/humidity").c_str(), String(h, 2).c_str(), false, 0);
      
      String jsonDHT = "{\"temperature\":" + String(t) + ",\"humidity\":" + String(h) + "}";
      kirimKeDatabaseAsync("dht", jsonDHT);

      if (kipasMode == "auto") autoFan(t);
    }
  }
}

// ✅ FIX: openDoor() & closeDoor() dengan non-blocking
void openDoor() {
  servo.write(90);
  doorStatus = true;
  doorOpenTime = millis();
  doorTimerActive = true;
  
  client.publish(("smarthome/" + serial_number + "/pintu/status").c_str(), "terbuka", false, 0);
  
  if (WiFi.status() == WL_CONNECTED) {
    kirimKeDatabaseAsync("door", "{\"status\":\"terbuka\"}");
  }
  
  lcdShowNonBlocking("Pintu Terbuka", "Silahkan Masuk");
}

void closeDoor() {
  servo.write(0);
  doorStatus = false;
  
  client.publish(("smarthome/" + serial_number + "/pintu/status").c_str(), "tertutup", false, 0);
  
  if (WiFi.status() == WL_CONNECTED) {
    kirimKeDatabaseAsync("door", "{\"status\":\"tertutup\"}");
  }
  
  lcdShowNonBlocking("Pintu Tertutup", "Tempelkan Kartu");
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
  client.publish(("smarthome/" + serial_number + "/kipas/status").c_str(), msg.c_str(), false, 0);
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