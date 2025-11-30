
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

// ================= WiFi & MQTT =================
WiFiClient net;
MQTTClient client;

// ✅ MODIFIED: WiFi credentials (dipakai sebagai fallback jika Preferences kosong)
const char ssid[] = "NAMA_WIFI_KAMU";
const char pass[] = "PASSWORD_WIFI_KAMU";
const String serial_number = "ESP32_SMARTHOME_001";

const char *mqtt_server = "ISI_BROKER_MQTT_KAMU";
const char *mqtt_username = "ISI_USERNAME_MQTT_KAMU";
const char *mqtt_password = "ISI_PASSWORD_MQTT_KAMU";

// ✅ NEW: Variables untuk WiFi dari Preferences
String wifiSSID = "";
String wifiPassword = "";

// ✅ NEW: Track WiFi status untuk publish saat berubah
String lastIP = "";
int lastRSSI = 0;
unsigned long lastWiFiStatusPublish = 0;
const unsigned long WIFI_STATUS_CHECK_INTERVAL = 5000;  // Check every 5 seconds

const char *serverUrl = "http://nama-website-kamu.infinityfreeapp.com/api/receive_data.php";
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

// ================= EEPROM (Preferences) =================
Preferences preferences;

// ✅ MODIFIED: Tambahkan namespace untuk WiFi
Preferences wifiPrefs;

const String keyCardCount = "cardCount";
const String keyCardPrefix = "card";
const String keyThresholdOn = "threshOn";
const String keyThresholdOff = "threshOff";
const String keyKipasMode = "kipasMode";

// ================= Timing & reconnect =================
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

// ✅ NEW: Function declarations untuk WiFi config
void loadWiFiConfig();
void saveWiFiConfig(const String &ssid, const String &password);
void publishWiFiStatus();
void checkWiFiStatusChange();
// kirim database ke web
void kirimKeDatabase(String type, String dataJson);
// ================= Utility: LCD helper =================
void lcdShow(const String &line1, const String &line2, unsigned long durationMs = 0) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(line1);
  lcd.setCursor(0, 1);
  lcd.print(line2);
  if (durationMs > 0) delay(durationMs);
}

// ================= Setup =================
void setup() {
  Serial.begin(115200);
  delay(200);

  Serial.println("\n\n========================================");
  Serial.println("ESP32 Smart Home - WiFi Config Edition");
  Serial.println("With Dynamic WiFi Configuration!");
  Serial.println("========================================\n");

  // Servo init
  servo.attach(pinServo, 500, 2400);
  servo.write(0);

  // Relay init
  pinMode(pinRelay, OUTPUT);
  digitalWrite(pinRelay, HIGH);

  // RFID init
  SPI.begin();
  mfrc522.PCD_Init();
  delay(200);

  // DHT22 init
  dht.begin();

  // LCD init
  lcd.init();
  lcd.backlight();
  lcdShow("Smart Home ESP32", "Init System...", 1500);

  // Preferences init
  preferences.begin("rfid", false);
  loadThresholds();

  // ✅ NEW: Load WiFi config dari Preferences
  loadWiFiConfig();

  // MQTT init
  client.begin(mqtt_server, net);
  client.onMessage(messageReceived);

  // ✅ MODIFIED: WiFi connect dengan config dari Preferences atau fallback ke hardcode
  if (wifiSSID.length() > 0) {
    Serial.println("📶 Using WiFi from Preferences:");
    Serial.println("   SSID: " + wifiSSID);
    WiFi.begin(wifiSSID.c_str(), wifiPassword.c_str());
    lcdShow("WiFi Config Mode", "SSID: " + wifiSSID.substring(0, 13), 1500);
  } else {
    Serial.println("📶 Using hardcoded WiFi (fallback):");
    Serial.println("   SSID: " + String(ssid));
    WiFi.begin(ssid, pass);
    lcdShow("WiFi Default", "SSID: " + String(ssid), 1500);
  }

  lastReconnectAttempt = 0;
}

// ================= Loop =================
void loop() {

  client.loop();

  // Reconnect logic
  if (!client.connected()) {
    if (millis() - lastReconnectAttempt >= RECONNECT_INTERVAL) {
      lastReconnectAttempt = millis();

      if (WiFi.status() != WL_CONNECTED) {
        Serial.println("WiFi disconnected. Attempting connect...");

        // ✅ MODIFIED: Retry dengan config yang benar
        if (wifiSSID.length() > 0) {
          WiFi.begin(wifiSSID.c_str(), wifiPassword.c_str());
        } else {
          WiFi.begin(ssid, pass);
        }
      } else {
        Serial.println("WiFi OK. Attempt MQTT connect...");
      }

      if (WiFi.status() == WL_CONNECTED) {
        String willTopic = "smarthome/status/" + serial_number;
        client.setWill(willTopic.c_str(), "offline", true, 1);

        String clientId = "esp32-" + serial_number;
        if (client.connect(clientId.c_str(), mqtt_username, mqtt_password)) {
          Serial.println("✅ MQTT Connected!");
          client.publish(willTopic.c_str(), "online", true, 1);

          // ✅ MODIFIED: Subscribe ke semua topics termasuk WiFi config
          client.subscribe(("smarthome/" + serial_number + "/#").c_str(), 1);

          // Publish initial states
          publishFanState();

          // ✅ NEW: Publish WiFi status saat connected
          publishWiFiStatus();

          lcdShow("WiFi & MQTT OK", "Tempelkan Kartu", 800);
        } else {
          Serial.println("❌ MQTT connect failed");
        }
      }
    }
  }

  // ✅ NEW: Check WiFi status changes
  checkWiFiStatusChange();

  // Handle RFID & DHT
  checkRFID();
  readDHT();

  // Auto close door
  if (doorTimerActive && (millis() - doorOpenTime >= DOOR_OPEN_MS)) {
    closeDoor();
    doorTimerActive = false;
  }

  delay(10);
}

void kirimKeDatabase(String type, String dataJson) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;

    // Mulai koneksi ke URL
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/json");

    // Bungkus data sesuai format yang diminta receive_data.php
    // Format: {"type": "...", "data": { ... }}
    String payload = "{\"type\":\"" + type + "\", \"data\":" + dataJson + "}";

    Serial.print("🌐 Mengirim ke Web: ");
    Serial.println(payload);

    int httpResponseCode = http.POST(payload);

    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.println("✅ Web Response: " + String(httpResponseCode));  // Harusnya 200
      // Kalau mau, bisa print `response` juga
    } else {
      Serial.print("❌ Web Error code: ");
      Serial.println(httpResponseCode);  // Kalau minus, berarti error koneksi/diblokir
    }
    http.end();
  } else {
    Serial.println("⚠️ WiFi Disconnected, gagal kirim ke Web");
  }
}


// ================= MQTT Callback =================
void messageReceived(String &topic, String &payload) {
  Serial.println("========================================");
  Serial.println("📩 MQTT Message Received");
  Serial.println("Topic: " + topic);
  Serial.println("Payload: " + payload);
  Serial.println("========================================");

  payload.trim();

  // ✅ NEW: Handle WiFi set_config
  if (topic == "smarthome/" + serial_number + "/wifi/set_config") {
    JsonDocument doc;
    DeserializationError error = deserializeJson(doc, payload);

    if (!error) {
      String newSSID = doc["ssid"].as<String>();
      String newPassword = doc["password"].as<String>();

      Serial.println("🔧 Received new WiFi config:");
      Serial.println("   SSID: " + newSSID);
      Serial.println("   Password: ********");

      // Save to Preferences
      saveWiFiConfig(newSSID, newPassword);

      // Publish status "restarting"
      client.publish(("smarthome/" + serial_number + "/wifi/status").c_str(),
                     "{\"status\":\"restarting\"}", true, 1);

      lcdShow("WiFi Config Set", "Restarting...", 2000);

      // Disconnect gracefully
      client.disconnect();
      WiFi.disconnect();

      delay(1000);

      // Restart ESP32
      ESP.restart();
    } else {
      Serial.println("❌ Failed to parse WiFi config JSON");
    }
    return;
  }

  // ✅ NEW: Handle WiFi get_status request
  if (topic == "smarthome/" + serial_number + "/wifi/get_status") {
    Serial.println("📊 WiFi status requested, publishing...");
    publishWiFiStatus();
    return;
  }

  // Servo manual
  if (topic == "smarthome/" + serial_number + "/servo") {
    int pos = payload.toInt();
    pos = constrain(pos, 0, 180);
    servo.write(pos);
    doorStatus = (pos > 45);
    lcdShow("Servo Pos: " + String(pos), doorStatus ? "Terbuka" : "Tertutup", 800);
    return;
  }

  // Kipas manual control
  if (topic == "smarthome/" + serial_number + "/kipas/control") {
    if (kipasMode != "manual") {
      Serial.println("⚠️ Cannot control fan: not in MANUAL mode");
      return;
    }

    if (payload == "on") {
      controlFan(true);
    } else if (payload == "off") {
      controlFan(false);
    }
    return;
  }

  // Kipas mode
  if (topic == "smarthome/" + serial_number + "/kipas/mode") {
    String newMode = payload;
    newMode.toLowerCase();

    if (kipasMode == newMode) {
      Serial.println("⏭️ Mode unchanged: " + newMode);
      return;
    }

    kipasMode = newMode;
    preferences.putString(keyKipasMode.c_str(), kipasMode);
    Serial.println("✅ Mode kipas changed to: " + kipasMode);

    publishFanState();

    lcdShow("Mode: " + kipasMode, kipasStatus ? "Kipas ON" : "Kipas OFF", 1000);
    return;
  }

  // Threshold suhu update
  if (topic == "smarthome/" + serial_number + "/kipas/threshold") {
    JsonDocument doc;
    DeserializationError error = deserializeJson(doc, payload);

    if (!error) {
      if (doc["on"].is<float>()) {
        tempThresholdOn = doc["on"].as<float>();
      }
      if (doc["off"].is<float>()) {
        tempThresholdOff = doc["off"].as<float>();
      }

      saveThresholds();
      Serial.printf("🌡️ Threshold updated: ON=%.1f°C, OFF=%.1f°C\n",
                    tempThresholdOn, tempThresholdOff);

      lcdShow("Threshold Update",
              String(tempThresholdOn, 1) + "/" + String(tempThresholdOff, 1), 1500);
    }
    return;
  }

  // Register kartu
  if (topic == "smarthome/" + serial_number + "/rfid/register") {
    String newUid = payload;
    newUid.toUpperCase();

    if (isCardRegistered(newUid)) {
      client.publish(("smarthome/" + serial_number + "/rfid/info").c_str(),
                     "{\"action\":\"add\",\"result\":\"exists\"}", true, 1);
      lcdShow("Kartu Sudah Ada", newUid, 1200);
      return;
    }

    int count = preferences.getInt(keyCardCount.c_str(), 0);
    String key = keyCardPrefix + String(count);
    preferences.putString(key.c_str(), newUid);
    preferences.putInt(keyCardCount.c_str(), count + 1);

    Serial.println("✅ Kartu ditambahkan: " + newUid);
    lcdShow("Kartu Ditambah", newUid, 1500);
    lcdShow("Pintu Tertutup", "Tempelkan Kartu", 800);

    String payloadMsg =
      "{\"action\":\"add\",\"uid\":\"" + newUid + "\",\"result\":\"ok\"}";
    client.publish(("smarthome/" + serial_number + "/rfid/info").c_str(),
                   payloadMsg.c_str(), true, 1);
    return;
  }

  // Remove kartu
  if (topic == "smarthome/" + serial_number + "/rfid/remove") {
    String remUid = payload;
    remUid.toUpperCase();
    int count = preferences.getInt(keyCardCount.c_str(), 0);
    bool found = false;

    for (int i = 0; i < count; i++) {
      String key = keyCardPrefix + String(i);
      String uid = preferences.getString(key.c_str(), "");
      if (uid == remUid) {
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
      client.publish(("smarthome/" + serial_number + "/rfid/info").c_str(),
                     "{\"action\":\"remove\",\"result\":\"ok\"}", true, 1);
      lcdShow("Kartu Dihapus", remUid, 1200);
    } else {
      client.publish(("smarthome/" + serial_number + "/rfid/info").c_str(),
                     "{\"action\":\"remove\",\"result\":\"not_found\"}", true, 1);
      lcdShow("Kartu Tidak Ada", remUid, 1200);
    }
  }
}

// ================= WiFi Config Functions =================

// ✅ NEW: Load WiFi config dari Preferences
void loadWiFiConfig() {
  wifiPrefs.begin("wifi", false);
  wifiSSID = wifiPrefs.getString("wifi_ssid", "");
  wifiPassword = wifiPrefs.getString("wifi_pass", "");
  wifiPrefs.end();

  if (wifiSSID.length() > 0) {
    Serial.println("📖 Loaded WiFi config from Preferences:");
    Serial.println("   SSID: " + wifiSSID);
    Serial.println("   Password: ********");
  } else {
    Serial.println("📖 No WiFi config in Preferences, using hardcoded");
  }
}

// ✅ NEW: Save WiFi config ke Preferences
void saveWiFiConfig(const String &ssid, const String &password) {
  wifiPrefs.begin("wifi", false);
  wifiPrefs.putString("wifi_ssid", ssid);
  wifiPrefs.putString("wifi_pass", password);
  wifiPrefs.end();

  Serial.println("💾 WiFi config saved to Preferences");
  Serial.println("   SSID: " + ssid);

  // Update global variables
  wifiSSID = ssid;
  wifiPassword = password;
}

// ✅ NEW: Publish WiFi status ke MQTT
void publishWiFiStatus() {
  if (WiFi.status() != WL_CONNECTED) {
    client.publish(("smarthome/" + serial_number + "/wifi/status").c_str(),
                   "{\"status\":\"disconnected\"}", false, 0);
    return;
  }

  String currentSSID = WiFi.SSID();
  String currentIP = WiFi.localIP().toString();
  int currentRSSI = WiFi.RSSI();

  // Create JSON payload
  JsonDocument doc;
  doc["status"] = "connected";
  doc["ssid"] = currentSSID;
  doc["ip"] = currentIP;
  doc["rssi"] = currentRSSI;

  String payload;
  serializeJson(doc, payload);

  bool success = client.publish(("smarthome/" + serial_number + "/wifi/status").c_str(),
                                payload.c_str(), false, 0);

  if (success) {
    Serial.println("📤 WiFi Status Published:");
    Serial.println("   " + payload);

    // Update last values
    lastIP = currentIP;
    lastRSSI = currentRSSI;
    lastWiFiStatusPublish = millis();
  }
}

// ✅ NEW: Check WiFi status changes dan publish jika berubah
void checkWiFiStatusChange() {
  if (millis() - lastWiFiStatusPublish < WIFI_STATUS_CHECK_INTERVAL) {
    return;
  }

  if (WiFi.status() != WL_CONNECTED) {
    if (lastIP.length() > 0) {
      // Was connected, now disconnected
      Serial.println("⚠️ WiFi disconnected!");
      client.publish(("smarthome/" + serial_number + "/wifi/status").c_str(),
                     "{\"status\":\"disconnected\"}", false, 0);
      lastIP = "";
      lastRSSI = 0;
      lastWiFiStatusPublish = millis();
    }
    return;
  }

  String currentIP = WiFi.localIP().toString();
  int currentRSSI = WiFi.RSSI();

  // Check if IP changed or RSSI changed significantly (>5 dBm)
  bool ipChanged = (currentIP != lastIP);
  bool rssiChanged = (abs(currentRSSI - lastRSSI) > 5);

  if (ipChanged || rssiChanged) {
    Serial.println("📊 WiFi status changed, publishing update...");
    if (ipChanged) {
      Serial.println("   IP: " + lastIP + " → " + currentIP);
    }
    if (rssiChanged) {
      Serial.println("   RSSI: " + String(lastRSSI) + " → " + String(currentRSSI));
    }
    publishWiFiStatus();
  }
}

// ================= Other Functions (unchanged) =================

void checkRFID() {
  if (!mfrc522.PICC_IsNewCardPresent()) return;
  if (!mfrc522.PICC_ReadCardSerial()) return;

  String cardUID = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) cardUID += "0";
    cardUID += String(mfrc522.uid.uidByte[i], HEX);
  }
  cardUID.toUpperCase();
  Serial.println("UID Kartu: " + cardUID);

  if (isCardRegistered(cardUID)) {
    client.publish(("smarthome/" + serial_number + "/rfid/access").c_str(),
                   "{\"status\":\"granted\"}", true, 1);
    openDoor();
  } else {
    client.publish(("smarthome/" + serial_number + "/rfid/access").c_str(),
                   "{\"status\":\"denied\"}", true, 1);
    lcdShow("Belum Terdaftar", cardUID, 1500);
  }
  // ✅ TAMBAHAN BARU: Kirim Log RFID ke Web
  String statusKartu = isCardRegistered(cardUID) ? "granted" : "denied";
  // JSON data: {"uid": "1234AB", "status": "granted"}
  String jsonRFID = "{\"uid\":\"" + cardUID + "\",\"status\":\"" + statusKartu + "\"}";
  kirimKeDatabase("rfid", jsonRFID);

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
}

void openDoor() {
  servo.write(90);
  doorStatus = true;
  doorOpenTime = millis();
  doorTimerActive = true;
  client.publish(("smarthome/" + serial_number + "/pintu/status").c_str(),
                 "terbuka", true, 1);
  Serial.println("Pintu terbuka!");
  lcdShow("Pintu Terbuka", "Silahkan Masuk", 800);

  // ✅ TAMBAHAN BARU: Log Pintu
  kirimKeDatabase("door", "{\"status\":\"terbuka\"}");
}

void closeDoor() {
  servo.write(0);
  doorStatus = false;
  client.publish(("smarthome/" + serial_number + "/pintu/status").c_str(),
                 "tertutup", true, 1);
  Serial.println("Pintu tertutup!");
  lcdShow("Pintu Tertutup", "Tempelkan Kartu", 800);

  // ✅ TAMBAHAN BARU: Log Pintu
  kirimKeDatabase("door", "{\"status\":\"tertutup\"}");
}

bool isCardRegistered(const String &cardUID) {
  int count = preferences.getInt(keyCardCount.c_str(), 0);
  for (int i = 0; i < count; i++) {
    String key = keyCardPrefix + String(i);
    String uid = preferences.getString(key.c_str(), "");
    if (uid == cardUID) return true;
  }
  return false;
}

void readDHT() {
  if (millis() - lastDHTRead >= DHT_INTERVAL) {
    lastDHTRead = millis();
    float h = dht.readHumidity();
    float t = dht.readTemperature();

    if (isnan(h) || isnan(t)) {
      Serial.println("❌ Gagal membaca DHT22");
      return;
    }

    Serial.printf("🌡 Suhu: %.2f°C | 💧 Kelembapan: %.2f%%\n", t, h);
    client.publish(("smarthome/" + serial_number + "/dht/temperature").c_str(),
                   String(t, 2).c_str(), true, 1);
    client.publish(("smarthome/" + serial_number + "/dht/humidity").c_str(),
                   String(h, 2).c_str(), true, 1);

    // ✅ TAMBAHAN BARU: Kirim ke Database Web
    // JSON data untuk dht: {"temperature": 28.5, "humidity": 70.2}
    String jsonDHT = "{\"temperature\":" + String(t) + ",\"humidity\":" + String(h) + "}";
    kirimKeDatabase("dht", jsonDHT);

    if (kipasMode == "auto") {
      autoFan(t);
    }
  }
}

void controlFan(bool turnOn) {
  if (kipasStatus == turnOn) {
    Serial.println("⏭️ Fan already " + String(turnOn ? "ON" : "OFF"));
    return;
  }

  kipasStatus = turnOn;
  digitalWrite(pinRelay, turnOn ? LOW : HIGH);
  Serial.println(turnOn ? "💨 Kipas ON" : "💤 Kipas OFF");

  publishFanState();
}

void autoFan(float temperature) {
  bool shouldTurnOn = (!kipasStatus && temperature >= tempThresholdOn);
  bool shouldTurnOff = (kipasStatus && temperature <= tempThresholdOff);

  if (shouldTurnOn) {
    controlFan(true);
    lcdShow("Kipas AUTO ON", String(temperature, 1) + " >= " + String(tempThresholdOn, 1), 1000);
  } else if (shouldTurnOff) {
    controlFan(false);
    lcdShow("Kipas AUTO OFF", String(temperature, 1) + " <= " + String(tempThresholdOff, 1), 1000);
  }
}

void publishFanState() {
  unsigned long now = millis();
  if (now - lastFanPublish < FAN_PUBLISH_COOLDOWN) {
    Serial.println("⏭️ Publish skipped (cooldown active)");
    return;
  }

  if (kipasStatus == lastKipasStatus && kipasMode == lastKipasMode) {
    Serial.println("⏭️ Publish skipped (state unchanged)");
    return;
  }

  lastFanPublish = now;
  lastKipasStatus = kipasStatus;
  lastKipasMode = kipasMode;

  String status = kipasStatus ? "on" : "off";
  String message = status + "," + kipasMode;

  String topic = "smarthome/" + serial_number + "/kipas/status";
  bool success = client.publish(topic.c_str(), message.c_str(), true, 1);

  if (success) {
    Serial.println("📤 Published: " + message + " → " + topic);
  } else {
    Serial.println("❌ Publish failed!");
  }
}

void saveThresholds() {
  preferences.putFloat(keyThresholdOn.c_str(), tempThresholdOn);
  preferences.putFloat(keyThresholdOff.c_str(), tempThresholdOff);
  Serial.println("💾 Threshold saved to EEPROM");
}

void loadThresholds() {
  tempThresholdOn = preferences.getFloat(keyThresholdOn.c_str(), 38.0);
  tempThresholdOff = preferences.getFloat(keyThresholdOff.c_str(), 30.0);
  kipasMode = preferences.getString(keyKipasMode.c_str(), "auto");

  lastKipasMode = kipasMode;
  lastKipasStatus = kipasStatus;

  Serial.printf("📖 Loaded threshold: ON=%.1f°C, OFF=%.1f°C, Mode=%s\n",
                tempThresholdOn, tempThresholdOff, kipasMode.c_str());
}
