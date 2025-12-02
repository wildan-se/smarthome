# Troubleshooting DHT Log di InfinityFree

## Masalah

Data DHT (suhu & kelembapan) tidak masuk ke database di hosting InfinityFree, padahal di localhost berhasil.

## Root Cause

InfinityFree memiliki batasan keamanan yang memblokir `file_get_contents('php://input')` yang digunakan untuk membaca JSON dari request body.

## Perbaikan yang Dilakukan

### 1. **File: `api/receive_data.php`**

#### A. Fix Input Handling

```php
// SEBELUM (Tidak kompatibel InfinityFree):
$data = json_decode(file_get_contents('php://input'), true);

// SETELAH (Kompatibel InfinityFree):
$rawInput = @file_get_contents('php://input');
$data = null;

if ($rawInput !== false && !empty($rawInput)) {
  $data = json_decode($rawInput, true);
} elseif (!empty($_POST)) {
  $data = $_POST;
}
```

#### B. Fix Payload Parsing

```php
// Support both JSON string dan array
$payload = [];
if (isset($data['data'])) {
  if (is_string($data['data'])) {
    $payload = json_decode($data['data'], true);
  } elseif (is_array($data['data'])) {
    $payload = $data['data'];
  }
}
```

#### C. Fix DHT Insert

```php
// SEBELUM:
INSERT INTO dht_logs (temperature, humidity) VALUES (?, ?)

// SETELAH (Explicit log_time):
INSERT INTO dht_logs (temperature, humidity, log_time) VALUES (?, ?, NOW())
```

### 2. **File: `assets/js/pages/dashboard.js`**

#### A. Fix AJAX Request

```javascript
// SEBELUM (JSON body - diblokir InfinityFree):
$.ajax({
  url: "api/receive_data.php",
  method: "POST",
  contentType: "application/json",
  data: JSON.stringify({
    type: "dht",
    data: { temperature: temp, humidity: hum },
  }),
});

// SETELAH (Form-encoded - kompatibel InfinityFree):
$.ajax({
  url: "api/receive_data.php",
  method: "POST",
  data: {
    type: "dht",
    data: JSON.stringify({
      temperature: temp,
      humidity: hum,
    }),
  },
});
```

#### B. Tambah Validasi & Logging

```javascript
// Validasi sebelum kirim
if (latestTemp < -50 || latestTemp > 80 || latestHum < 0 || latestHum > 100) {
  console.error("❌ DHT values out of range, skipping save");
  return;
}

// Detail error logging
error: function (xhr) {
  console.error("❌ Failed to save DHT - Status:", xhr.status);
  console.error("❌ Response:", xhr.responseText);
}
```

## Testing di InfinityFree

### 1. Upload File ke Hosting

Upload file-file berikut:

- `api/receive_data.php` (updated)
- `assets/js/pages/dashboard.js` (updated)
- `api/test_dht_insert.php` (new - untuk testing)

### 2. Test Database Connection

Akses: `https://yourdomain.com/api/test_dht_insert.php`

**Expected Output:**

```json
{
  "status": "Test completed",
  "tests": {
    "connection": "✅ Connected",
    "table_exists": "✅ Table exists",
    "table_structure": ["id", "temperature", "humidity", "log_time"],
    "insert": "✅ Success - ID: 123",
    "verify": "✅ Data verified",
    "cleanup": "✅ Test data removed",
    "now_function": "✅ NOW() works: 2025-12-02 10:30:45",
    "php_input": "❌ php://input blocked" // Normal di InfinityFree
  }
}
```

### 3. Test Real-Time via Dashboard

1. Buka halaman Dashboard: `https://yourdomain.com/index.php`
2. Buka Browser Console (F12)
3. Tunggu ESP32 mengirim data DHT via MQTT
4. Lihat console log:

**Expected Console Output:**

```
🌡️ Temperature received: 28.5°C
💧 Humidity received: 65.0%
💾 Saving DHT to DB: 28.5°C, 65.0%
✅ DHT saved to database: {success: true, message: "DHT data saved", id: 124}
```

### 4. Verifikasi di Halaman Log

1. Buka halaman Log: `https://yourdomain.com/log.php`
2. Tab "Log Suhu & Kelembapan"
3. Data baru harus muncul di tabel

## Debug Checklist

### Jika Data Masih Tidak Masuk:

#### A. Cek Console Browser (F12)

```javascript
// Cek apakah MQTT menerima data:
📨 MQTT: smarthome/ESP001/dht/temperature => 28.5
📨 MQTT: smarthome/ESP001/dht/humidity => 65.0

// Cek apakah function dipanggil:
🌡️ Temperature received: 28.5°C
💧 Humidity received: 65.0%
💾 Saving DHT to DB: 28.5°C, 65.0%

// Cek response dari server:
✅ DHT saved to database: {...}
// ATAU
❌ Failed to save DHT - Status: 500
❌ Response: {"error": "..."}
```

#### B. Cek Database Langsung

Via phpMyAdmin di InfinityFree:

```sql
SELECT * FROM dht_logs ORDER BY log_time DESC LIMIT 10;
```

#### C. Cek Error Log

1. InfinityFree Control Panel → Error Logs
2. Cari error terkait `receive_data.php`

#### D. Cek File Permissions

Pastikan file `api/receive_data.php` permissions: `644`

### Common Issues:

1. **"php://input blocked"**

   - ✅ Normal di InfinityFree
   - ✅ Sudah diperbaiki dengan fallback `$_POST`

2. **"NOW() not working"**

   - ✅ Sudah ditambahkan explicit `log_time` column
   - Fallback: Gunakan PHP `date()` jika perlu

3. **"CORS Error"**

   - Tambahkan di `receive_data.php`:

   ```php
   header('Access-Control-Allow-Origin: *');
   header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
   ```

4. **"Invalid data"**

   - Cek console: Apakah `type` dan `data` terkirim?
   - Cek format: `{type: "dht", data: "{\"temperature\":28.5,\"humidity\":65}"}`

5. **"Database connection failed"**
   - Cek `config/config.php` credentials
   - InfinityFree format: `servername_dbname`, `servername_username`

## InfinityFree Limitations

### Yang TIDAK Bisa:

- ❌ `file_get_contents('php://input')` - **DIBLOKIR**
- ❌ `exec()`, `shell_exec()` - Disabled
- ❌ Custom PHP extensions
- ❌ Long-running scripts (30s max)

### Yang BISA:

- ✅ `$_POST`, `$_GET` - Standard input
- ✅ MySQL queries (dengan batasan)
- ✅ `NOW()`, `CURRENT_TIMESTAMP`
- ✅ JSON encode/decode
- ✅ AJAX requests

## Performance Tips

1. **Reduce Insert Frequency**

   - DHT sensor kirim setiap 2 detik
   - Pertimbangkan batching (simpan setiap 30 detik)

2. **Add Index**

   ```sql
   ALTER TABLE dht_logs ADD INDEX idx_log_time (log_time);
   ```

3. **Auto-cleanup Old Data**
   ```sql
   DELETE FROM dht_logs
   WHERE log_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
   ```

## Support

Jika masih bermasalah:

1. Jalankan `test_dht_insert.php` dan kirim hasilnya
2. Cek browser console dan screenshot
3. Cek InfinityFree error logs

---

**Last Updated:** 2 Desember 2025
**Version:** 2.0 (InfinityFree Compatible)
