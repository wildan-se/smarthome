# 🔧 Rekomendasi Fix ESP32 untuk Bug Mode Loop

## 📋 Problem Analysis

Bug mode loop (auto ↔ manual bolak-balik) kemungkinan disebabkan oleh:

1. **ESP32 re-publish mode** setelah menerima dari web
2. **Conflicting topics** (`/kipas/mode` vs `/kipas/status`)
3. **Auto-mode logic** yang trigger perubahan mode tanpa kontrol

---

## ✅ Solusi ESP32 (3 Opsi)

### **Opsi 1: Jangan Re-Publish Mode (RECOMMENDED)**

ESP32 **HANYA** terima mode dari web, **JANGAN** publish kembali via `/kipas/mode`.

#### **BEFORE (Bug):**

```cpp
void onMqttMessage(String topic, String payload) {
  if (topic.endsWith("/kipas/mode")) {
    String mode = payload; // "auto" atau "manual"
    currentMode = mode;

    // ❌ BUG: Re-publish mode
    client.publish((topicRoot + "/kipas/mode").c_str(), mode.c_str());
  }
}
```

#### **AFTER (Fixed):**

```cpp
void onMqttMessage(String topic, String payload) {
  if (topic.endsWith("/kipas/mode")) {
    String mode = payload; // "auto" atau "manual"
    currentMode = mode;

    // ✅ FIX: Jangan re-publish mode
    // Hanya save ke EEPROM atau variable
    Serial.println("Mode changed to: " + mode);

    // OPTIONAL: Publish via /kipas/status saja (bukan /kipas/mode)
    String status = (fanStatus == HIGH) ? "on" : "off";
    String statusMsg = status + "," + mode;
    client.publish((topicRoot + "/kipas/status").c_str(), statusMsg.c_str());
  }
}
```

---

### **Opsi 2: Publish Status+Mode Gabungan (RECOMMENDED)**

ESP32 hanya publish via **1 topic**: `/kipas/status` dengan format `"status,mode"`.

#### **Implementasi:**

```cpp
// Function untuk publish status & mode
void publishStatus() {
  String status = (fanStatus == HIGH) ? "on" : "off";
  String mode = currentMode; // "auto" atau "manual"

  // Format: "on,auto" atau "off,manual"
  String message = status + "," + mode;

  client.publish((topicRoot + "/kipas/status").c_str(), message.c_str());
  Serial.println("Published: " + message);
}

// Panggil function ini saat:
// 1. Status kipas berubah (ON/OFF)
// 2. Mode berubah (auto/manual)
// 3. DHT auto-control trigger perubahan

void onMqttMessage(String topic, String payload) {
  if (topic.endsWith("/kipas/mode")) {
    currentMode = payload;
    publishStatus(); // Publish via /kipas/status saja
  }

  if (topic.endsWith("/kipas/control")) {
    if (payload == "on") {
      digitalWrite(FAN_PIN, HIGH);
      fanStatus = HIGH;
    } else {
      digitalWrite(FAN_PIN, LOW);
      fanStatus = LOW;
    }
    publishStatus(); // Publish via /kipas/status saja
  }
}

// DHT Auto Control
void checkAutoMode() {
  if (currentMode != "auto") return;

  float temp = dht.readTemperature();
  bool shouldTurnOn = (temp > thresholdOn && fanStatus == LOW);
  bool shouldTurnOff = (temp < thresholdOff && fanStatus == HIGH);

  if (shouldTurnOn) {
    digitalWrite(FAN_PIN, HIGH);
    fanStatus = HIGH;
    publishStatus(); // Publish 1x saja
  } else if (shouldTurnOff) {
    digitalWrite(FAN_PIN, LOW);
    fanStatus = LOW;
    publishStatus(); // Publish 1x saja
  }
}
```

---

### **Opsi 3: Tambahkan Source Tag (ADVANCED)**

Tambahkan identifier untuk track siapa yang trigger perubahan.

#### **Format Message:**

- Web → ESP32: `"manual"` atau `"auto"`
- ESP32 → Web: `"manual,src:esp32"` atau `"auto,src:esp32"`

#### **ESP32 Code:**

```cpp
void onMqttMessage(String topic, String payload) {
  if (topic.endsWith("/kipas/mode")) {
    // Parse payload: "manual" atau "manual,src:web"
    int commaIndex = payload.indexOf(",");
    String mode = (commaIndex > 0) ? payload.substring(0, commaIndex) : payload;
    String source = (commaIndex > 0) ? payload.substring(commaIndex + 1) : "unknown";

    currentMode = mode;
    Serial.println("Mode: " + mode + " | Source: " + source);

    // Jika dari web, jangan re-publish
    if (source.indexOf("web") >= 0) {
      Serial.println("Mode change from web, skip re-publish");
      return;
    }

    // Jika dari ESP32 internal (auto-mode, button), publish dengan tag
    String statusMsg = (fanStatus == HIGH ? "on" : "off") + "," + mode + ",src:esp32";
    client.publish((topicRoot + "/kipas/status").c_str(), statusMsg.c_str());
  }
}
```

#### **Web Code Update (Optional):**

```javascript
// Publish with source tag
client.publish(`${topicRoot}/kipas/mode`, newMode + ",src:web");

// Ignore messages with src:web
function handleModeChange(msg) {
  const parts = msg.toLowerCase().split(",");
  const mode = parts[0];
  const source = parts.find((p) => p.startsWith("src:"));

  if (source === "src:web") {
    console.log("Ignoring self-originated message");
    return;
  }

  // Process external mode change
  // ...
}
```

---

## 🎯 Checklist Debugging ESP32

Tambahkan Serial debugging untuk track message flow:

```cpp
void onMqttMessage(String topic, String payload) {
  Serial.println("========================================");
  Serial.println("MQTT Message Received:");
  Serial.println("Topic: " + topic);
  Serial.println("Payload: " + payload);
  Serial.println("Current Mode: " + currentMode);
  Serial.println("Fan Status: " + String(fanStatus == HIGH ? "ON" : "OFF"));
  Serial.println("========================================");

  // ... rest of code
}

void publishStatus() {
  String message = ...;
  Serial.println(">>> PUBLISHING: " + message + " to " + topicRoot + "/kipas/status");
  client.publish(...);
}
```

---

## 📊 Testing Flow

### **Test 1: Mode Manual → Auto**

```
1. Web: Klik "AUTO"
2. Serial Monitor ESP32:
   - Receive: /kipas/mode = "auto"
   - Current Mode: manual → auto
   - ✅ TIDAK boleh publish kembali via /kipas/mode
   - ✅ BOLEH publish via /kipas/status = "on,auto" (1x saja)
3. Web: Mode berubah ke AUTO (1x update)
4. ✅ PASS jika tidak ada loop
```

### **Test 2: Kontrol Manual ON/OFF**

```
1. Web: Mode MANUAL, klik "Nyalakan"
2. Serial Monitor ESP32:
   - Receive: /kipas/control = "on"
   - Fan: OFF → ON
   - Publish: /kipas/status = "on,manual" (1x)
3. Web: Status berubah ON (1x update)
4. ✅ PASS jika tidak ada loop
```

### **Test 3: Auto Mode DHT Control**

```
1. Web: Mode AUTO
2. ESP32: Suhu naik > threshold
3. Serial Monitor:
   - Auto control triggered
   - Fan: OFF → ON
   - Publish: /kipas/status = "on,auto" (1x)
4. Web: Status berubah ON (1x update)
5. ✅ PASS jika tidak ada loop
```

---

## 🚨 Common Mistakes di ESP32

### ❌ **Mistake 1: Double Publish**

```cpp
// BAD - Publish 2 kali untuk 1 event
if (topic.endsWith("/kipas/mode")) {
  currentMode = payload;
  client.publish((topicRoot + "/kipas/mode").c_str(), payload.c_str()); // ❌ Publish 1

  String status = ...;
  client.publish((topicRoot + "/kipas/status").c_str(), status.c_str()); // ❌ Publish 2
}
```

### ❌ **Mistake 2: Auto-Mode Publish Loop**

```cpp
// BAD - Publish setiap DHT reading
void loop() {
  float temp = dht.readTemperature();
  if (currentMode == "auto") {
    if (temp > 30) {
      digitalWrite(FAN_PIN, HIGH);
      publishStatus(); // ❌ Publish setiap loop!
    }
  }
}
```

### ✅ **Fix: Only Publish on State Change**

```cpp
void loop() {
  static bool lastFanStatus = LOW;

  float temp = dht.readTemperature();
  if (currentMode == "auto") {
    bool shouldBeOn = (temp > thresholdOn);

    if (shouldBeOn && fanStatus == LOW) {
      digitalWrite(FAN_PIN, HIGH);
      fanStatus = HIGH;

      if (lastFanStatus != fanStatus) {
        publishStatus(); // ✅ Publish hanya saat STATE CHANGE
        lastFanStatus = fanStatus;
      }
    }
  }
}
```

---

## 📝 Final Recommendation

**Gunakan kombinasi:**

1. ✅ **Web**: Sudah fixed dengan pending state & MQTT dedupe
2. ✅ **ESP32**: Implementasi **Opsi 1** atau **Opsi 2** (jangan re-publish mode)
3. ✅ **Testing**: Gunakan Serial Monitor untuk debug message flow
4. ✅ **Validate**: Pastikan setiap event hanya trigger 1x publish

---

## 📞 Next Steps

1. **Review ESP32 code** saat ini, cari di mana publish dilakukan
2. **Implementasi fix** sesuai Opsi 1 atau 2
3. **Upload sketch** ke ESP32
4. **Test** dengan Serial Monitor + Web UI
5. **Report** jika masih ada loop

---

**Generated:** 2025-11-08  
**Commit:** a094c54  
**Files:** `assets/js/pages/fan.js`
