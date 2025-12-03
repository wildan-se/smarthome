# 🧪 RFID Data Flow Testing Guide

## ✅ Yang Sudah Diperbaiki

### 1. ESP32 Firmware (`esp32-smarthome.ino`)

**Masalah Lama:**

- RFID kirim JSON body: `{"type":"rfid", "data":{"uid":"XXX","status":"granted"}}`
- InfinityFree BLOCK `php://input` untuk JSON body

**Perbaikan Baru:**

```arduino
// Line ~391-420: Parsing RFID data
if (type == "rfid") {
  // Parse dari: {"uid":"AABBCCDD","status":"granted"}
  String uid = extract dari dataJson
  String status = extract dari dataJson

  // Kirim form-encoded (BUKAN JSON!)
  payload = "type=rfid&uid=" + uid + "&status=" + status;
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
}
```

**Debug Output:**

```
🔍 RFID Parse Debug:
  Input: {"uid":"AABBCCDD","status":"granted"}
  UID parsed: AABBCCDD
  Status parsed: granted
  Payload: type=rfid&uid=AABBCCDD&status=granted
```

### 2. Backend API (`api/receive_data.php`)

**Masalah Lama:**

- Hanya cek `$payload['uid']` dari `$data['data']`
- Tidak cek `$_POST['uid']` langsung dari form-encoded

**Perbaikan Baru:**

```php
// Line ~70-72: Support form-encoded RFID
$uid = isset($data['uid']) ? $data['uid'] : (isset($payload['uid']) ? $payload['uid'] : null);
$status = isset($data['status']) ? $data['status'] : (isset($payload['status']) ? $payload['status'] : null);
```

Sekarang support 2 format:

1. ✅ Form-encoded: `type=rfid&uid=XXX&status=granted` (PRIORITAS)
2. ✅ JSON fallback: `{"type":"rfid","data":{"uid":"XXX","status":"granted"}}`

### 3. Frontend Auto-Refresh (`assets/js/pages/rfid.js`)

**Fitur:**

- ✅ Page Visibility API (3s saat tab visible, 15s saat hidden)
- ✅ Badge indicator (Auto 3s / Auto 15s)
- ✅ Last update timestamp
- ✅ Console log untuk debugging

## 🔬 Testing Steps

### Step 1: Upload ESP32 Firmware

```bash
1. Buka Arduino IDE
2. Buka esp32-smarthome.ino
3. Upload ke ESP32
4. Buka Serial Monitor (115200 baud)
```

### Step 2: Test RFID Tap

```
EXPECTED OUTPUT di Serial Monitor:
========== RFID DEBUG ==========
UID RAW: AABBCCDD
UID Length: 8
Total registered cards: 1
  Card[0]: AABBCCDD
✅ Card GRANTED: AABBCCDD
================================
🔍 RFID Parse Debug:
  Input: {"uid":"AABBCCDD","status":"granted"}
  UID parsed: AABBCCDD
  Status parsed: granted
  Payload: type=rfid&uid=AABBCCDD&status=granted
✅ Web OK: 200 (rfid)
```

### Step 3: Cek Database Log

```sql
-- Cek data masuk ke database
SELECT * FROM rfid_logs ORDER BY access_time DESC LIMIT 5;

-- Expected result:
-- uid: AABBCCDD
-- status: granted
-- access_time: 2025-12-03 10:30:45
```

### Step 4: Cek UI Auto-Refresh

```
1. Buka rfid.php di browser
2. Buka Browser Console (F12)
3. Tap kartu RFID di ESP32

EXPECTED di Console:
🔄 Auto-refreshing RFID data (page visible)...
✅ Badge updated to: Auto 3s (success)

4. Switch tab (hide page)
EXPECTED:
🙈 Page hidden - switching to slow refresh (15s)
✅ Badge updated to: Auto 15s (secondary)

5. Switch back (show page)
EXPECTED:
👁️ Page visible - switching to fast refresh (3s)
🔄 Auto-refreshing RFID data (page visible)...
```

### Step 5: Verifikasi Data Muncul

```
1. Tap RFID di ESP32
2. Tunggu max 3 detik (jika tab visible)
3. Lihat tabel "RFID Access History"
4. Expected: Baris baru dengan UID, Name, Status, Time
```

## 🐛 Debugging

### Jika Serial Monitor Tidak Muncul "✅ Web OK: 200"

```arduino
// Cek di Serial Monitor:
- Apakah WiFi connected?
- Apakah URL server benar?
- Apakah parsing UID dan status benar?
```

### Jika Database Kosong

```php
// Cek InfinityFree error log:
// Expected: "RFID received: uid=XXX, status=granted"
// Expected: "RFID log inserted: id=123, uid=XXX, status=granted"

// Jika tidak ada log, cek:
1. Apakah Content-Type = application/x-www-form-urlencoded?
2. Apakah $_POST['type'] = 'rfid'?
3. Apakah $_POST['uid'] dan $_POST['status'] ada?
```

### Jika UI Tidak Update

```javascript
// Cek Browser Console:
// Expected logs setiap 3 detik (page visible):
// 🔄 Auto-refreshing RFID data (page visible)...

// Jika tidak ada, cek:
1. Apakah badge element ada? (inspect #autoRefreshBadge)
2. Apakah Page Visibility API berjalan?
3. Apakah API endpoint rfid_crud.php?action=getlogs return data?
```

## ✅ Checklist Final

- [ ] Serial Monitor: "✅ Web OK: 200 (rfid)"
- [ ] Database: Ada entry baru di `rfid_logs`
- [ ] InfinityFree Log: "RFID received" + "RFID log inserted"
- [ ] Browser Console: Auto-refresh logs setiap 3/15 detik
- [ ] UI Badge: Tampil "Auto 3s" (hijau) atau "Auto 15s" (abu)
- [ ] Tabel History: Baris baru muncul dalam 3 detik
- [ ] Door Activity: Jika access granted, muncul "rfid" source

## 📊 Data Flow Diagram

```
ESP32 RFID Tap
    ↓
checkRFID() - {"uid":"XXX","status":"granted"}
    ↓
kirimKeDatabaseSync("rfid", jsonData)
    ↓
Parse JSON → "type=rfid&uid=XXX&status=granted"
    ↓
HTTP POST (form-encoded) → receive_data.php
    ↓
$_POST fallback (InfinityFree compatible!)
    ↓
INSERT INTO rfid_logs (uid, status)
    ↓
    ├─→ RFID Access History (via rfid_crud.php)
    └─→ Door Activity (if granted, via door_status)
    ↓
Page Visibility API auto-refresh (3s/15s)
    ↓
UI Update dalam browser
```

## 🎯 Success Criteria

✅ **ESP32**: Parsing JSON correct, POST form-encoded
✅ **Backend**: Terima `$_POST`, insert to DB
✅ **Frontend**: Auto-refresh working, badge update
✅ **Database**: Data tersimpan di `rfid_logs`
✅ **UI**: Tabel update real-time (3s visible, 15s hidden)
