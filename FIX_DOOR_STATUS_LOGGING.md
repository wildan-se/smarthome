# 🔧 Fix Door Status Logging - RFID Activity Not Appearing

## ❌ MASALAH SEBELUMNYA

Ketika tap kartu RFID dan pintu terbuka, data tidak muncul di "Riwayat Aktivitas Pintu"

## ✅ ROOT CAUSE DITEMUKAN

**InfinityFree hosting BLOCK `php://input`** untuk membaca JSON body!

### Alur Data Sebelumnya (GAGAL):

```
ESP32 → HTTP POST (JSON body) → InfinityFree Block php://input → $_POST kosong → Data tidak tersimpan
```

### Alur Data Sekarang (BENAR):

```
ESP32 → HTTP POST (form-encoded) → $_POST langsung → Database → MQTT → Frontend reload
```

---

## 🔄 PERUBAHAN YANG DILAKUKAN

### 1. **ESP32 Firmware** (`esp32-smarthome.ino`)

**SEBELUM**: Kirim semua data sebagai JSON body

```cpp
http.addHeader("Content-Type", "application/json");
String payload = "{\"type\":\"" + type + "\", \"data\":" + dataJson + "}";
```

**SESUDAH**: Door status kirim sebagai **form-encoded** (seperti DHT)

```cpp
if (type == "door") {
  // Parse status dan source dari dataJson
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  payload = "type=door&status=" + status + "&source=" + source;
} else {
  // JSON untuk type lain
  http.addHeader("Content-Type", "application/json");
  payload = "{\"type\":\"" + type + "\", \"data\":" + dataJson + "}";
}
```

### 2. **Backend API** (`api/receive_data.php`)

**SEBELUM**: Hanya baca dari `$payload` (hasil decode JSON)

```php
$status = isset($payload['status']) ? $payload['status'] : null;
$source = isset($payload['source']) ? $payload['source'] : 'unknown';
```

**SESUDAH**: Prioritas baca dari `$_POST` (form-encoded)

```php
$status = isset($_POST['status']) ? $_POST['status'] :
         (isset($payload['status']) ? $payload['status'] : null);
$source = isset($_POST['source']) ? $_POST['source'] :
         (isset($payload['source']) ? $payload['source'] : 'unknown');
```

### 3. **Frontend Auto-Reload** (`assets/js/pages/door.js`)

Sudah ditambahkan di fix sebelumnya:

```javascript
function handleDoorStatus(msg) {
  // ... update UI ...

  // ✅ Reload logs when status changes
  console.log("🔄 Door status changed, reloading logs...");
  setTimeout(() => {
    loadDoorLogs();
  }, 800);
}
```

---

## 📤 FILE YANG HARUS DI-UPLOAD

### Upload ke InfinityFree:

1. ✅ **`api/receive_data.php`** - Backend yang support form-encoded
2. ✅ **`api/test_door_insert.php`** - Script untuk test database

### Upload ke ESP32:

3. ✅ **`esp32-smarthome.ino`** - Firmware dengan form-encoded POST

### Sudah di-upload sebelumnya (optional, cek ulang):

4. ⚠️ **`assets/js/pages/door.js`** - Frontend auto-reload (sudah diupload sebelumnya)
5. ⚠️ **`api/door_log.php`** - Auto-create column (sudah diupload sebelumnya)

---

## 🧪 CARA TESTING

### Step 1: Test Database Manual

1. Buka di browser: `http://koneksipintar.infinityfreeapp.com/api/test_door_insert.php`
2. Harus muncul:
   ```
   ✅ Column 'source' EXISTS
   ✅ Insert SUCCESS - ID: xxx
   ✅ Insert SUCCESS - ID: xxx
   ✅ Simulated ESP32 POST SUCCESS
   ```

### Step 2: Test ESP32 POST

1. Upload firmware ke ESP32
2. Buka Serial Monitor (115200 baud)
3. Tap kartu RFID
4. Harus muncul:
   ```
   ✅ RFID: XXXXXXXX
   ✅ Akses Diterima
   ✅ MQTT: smarthome/ESP32-XXXXX/pintu/status = terbuka
   ✅ Web: 200 (door)
   ```

### Step 3: Test Frontend

1. Buka `kontrol.php` di browser
2. Buka Console (F12)
3. Tap kartu RFID
4. Console harus menampilkan:
   ```
   📨 MQTT: smarthome/ESP32-XXXXX/pintu/status = terbuka
   🚪 Door status: terbuka
   🔄 Door status changed, reloading logs...
   📊 Loading door logs...
   ```
5. Table "Riwayat Aktivitas Pintu" harus reload dan muncul baris baru:
   ```
   🔓 Terbuka | 🪪 RFID | 02/12/2025 21:30:45
   ```

### Step 4: Test via MQTT.fx (Optional)

1. Subscribe ke topic: `smarthome/ESP32-XXXXX/pintu/status`
2. Tap kartu RFID
3. Harus terima message: `terbuka`

---

## 🔍 TROUBLESHOOTING

### Jika masih belum muncul di tabel:

#### A. Cek Database

1. Login phpMyAdmin di InfinityFree
2. Buka table `door_status`
3. Cek apakah ada kolom `source`?

   - ❌ Jika TIDAK: Jalankan `test_door_insert.php` untuk auto-create
   - ✅ Jika ADA: Lanjut cek data

4. Cek data terakhir:
   ```sql
   SELECT * FROM door_status ORDER BY updated_at DESC LIMIT 5;
   ```
   - Harus ada data dengan `source = 'rfid'` atau `'auto'`

#### B. Cek ESP32 Serial Monitor

```
⚠️ Jika muncul "Web Timeout (door)":
- Cek WiFi connection
- Cek URL serverUrl benar
- Cek InfinityFree tidak down

✅ Jika muncul "Web: 200 (door)":
- HTTP POST berhasil
- Masalah ada di database atau frontend
```

#### C. Cek Browser Console

```
⚠️ Jika TIDAK muncul "🔄 Door status changed":
- MQTT message tidak diterima
- Cek koneksi MQTT di Network tab
- Pastikan subscribe ke topic yang benar

✅ Jika muncul "🔄 Door status changed":
- Frontend sudah reload
- Cek apakah API door_log.php mengembalikan data
```

#### D. Cek InfinityFree Error Log

1. Login cPanel InfinityFree
2. Buka Error Logs
3. Cari error dari `receive_data.php`
4. Harus ada log:
   ```
   Door status received: status=terbuka, source=rfid (method: POST)
   Door status inserted: id=xxx
   ```

---

## 📊 DATA FLOW LENGKAP

```
┌─────────────────────────────────────────────────────────────┐
│                    RFID TAP di ESP32                        │
└─────────────────────────────────────────────────────────────┘
                            ↓
    ┌───────────────────────────────────────────────┐
    │  ESP32: openDoor()                            │
    │  - Servo 90° (buka pintu)                     │
    │  - doorStatus = true                          │
    └───────────────────────────────────────────────┘
                            ↓
            ┌───────────────────────────────┐
            │  MQTT Publish                 │
            │  Topic: smarthome/.../status  │
            │  Message: "terbuka"           │
            └───────────────────────────────┘
                            ↓
            ┌───────────────────────────────┐
            │  HTTP POST (form-encoded)     │
            │  type=door                    │
            │  status=terbuka               │
            │  source=rfid                  │
            └───────────────────────────────┘
                            ↓
    ┌───────────────────────────────────────────────┐
    │  InfinityFree: receive_data.php               │
    │  - Baca $_POST['status'] = terbuka            │
    │  - Baca $_POST['source'] = rfid               │
    │  - INSERT INTO door_status                    │
    │  - Log: "Door status inserted: id=xxx"        │
    └───────────────────────────────────────────────┘
                            ↓
            ┌───────────────────────────────┐
            │  Database: door_status        │
            │  id | status  | source | time │
            │  15 | terbuka | rfid   | now  │
            └───────────────────────────────┘
                            ↓
    ┌───────────────────────────────────────────────┐
    │  Frontend: door.js (MQTT Subscribe)           │
    │  - Terima message: "terbuka"                  │
    │  - handleDoorStatus("terbuka")                │
    │  - Update icon jadi 🔓                        │
    │  - setTimeout(() => loadDoorLogs(), 800)      │
    └───────────────────────────────────────────────┘
                            ↓
            ┌───────────────────────────────┐
            │  AJAX GET door_log.php        │
            │  - Ambil data terbaru         │
            │  - Termasuk yang source=rfid  │
            └───────────────────────────────┘
                            ↓
    ┌───────────────────────────────────────────────┐
    │  Table "Riwayat Aktivitas Pintu" UPDATE       │
    │  🔓 Terbuka  | 🪪 RFID | 02/12/2025 21:30:45  │
    │  🔒 Tertutup | 🤖 Auto | 02/12/2025 21:25:40  │
    └───────────────────────────────────────────────┘
```

---

## ✅ CHECKLIST FINAL

Sebelum declare "BERHASIL", pastikan:

- [ ] File `api/receive_data.php` sudah di-upload ke InfinityFree
- [ ] File `api/test_door_insert.php` berjalan dan menampilkan ✅
- [ ] Kolom `source` ada di table `door_status` (cek phpMyAdmin)
- [ ] Firmware ESP32 sudah di-upload ulang
- [ ] ESP32 Serial Monitor menampilkan "✅ Web: 200 (door)"
- [ ] Browser Console menampilkan "🔄 Door status changed, reloading logs..."
- [ ] Table di `kontrol.php` menampilkan baris baru dengan badge 🪪 RFID
- [ ] Data di `log.php` juga menampilkan history RFID access

---

## 📝 CATATAN PENTING

1. **Kenapa Sebelumnya Gagal?**
   - InfinityFree FREE hosting memblokir `file_get_contents('php://input')`
   - ESP32 kirim JSON body → Server tidak bisa baca → Data hilang
2. **Kenapa Sekarang Berhasil?**
   - ESP32 kirim **form-encoded** (seperti form HTML biasa)
   - Server baca dari `$_POST` langsung → Tidak perlu php://input
3. **Perubahan Kunci**:
   - ✅ ESP32: JSON → form-encoded untuk door status
   - ✅ PHP: Baca dari `$_POST` prioritas pertama
   - ✅ JS: Auto-reload table saat status berubah (sudah ada sebelumnya)

---

**KESIMPULAN**: Masalah utama adalah **InfinityFree hosting restrictions**, bukan logic code. Sekarang sudah disesuaikan dengan keterbatasan hosting.
