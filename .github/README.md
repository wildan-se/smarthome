
# Sistem Smart Home IoT (ESP32 + Dashboard Web PHP)

![Status Pengembangan](https://img.shields.io/badge/status-development-yellow)
![Lisensi](https://img.shields.io/badge/license-MIT-blue.svg)
![GitHub stars](https://img.shields.io/github/stars/NAMA_ANDA/NAMA_REPO_ANDA?style=social)
![GitHub forks](https://img.shields.io/github/forks/NAMA_ANDA/NAMA_REPO_ANDA?style=social)

Solusi full-stack untuk manajemen dan otomatisasi smart home. Proyek ini menggabungkan firmware **ESP32** yang andal untuk kontrol perangkat keras (akses RFID, sensor suhu, dan kipas) dengan **dashboard web berbasis PHP (AdminLTE)** yang intuitif untuk monitoring, manajemen, dan logging data.

Proyek ini dirancang untuk menjadi sistem **IoT Smart Home** yang modular, aman, dan mudah dikelola, berfokus pada fungsionalitas dunia nyata seperti kontrol akses dan iklim.

---

## 🧭 Daftar Isi

* [Fitur dan Fungsionalitas](#-fitur-dan-fungsionalitas)
* [Tumpukan Teknologi](#-tumpukan-teknologi)
* [Panduan Instalasi](#-panduan-instalasi)
    * [Backend (Web Server)](#backend-web-server)
    * [Firmware (ESP32)](#firmware-esp32)
* [Contoh Penggunaan (API)](#-contoh-penggunaan-api--cuplikan-kode)
    * [ESP32 -> Server (Kirim Data Sensor)](#esp32--server-kirim-data-sensor)
    * [ESP32 <- Server (Ambil Perintah)](#esp32--server-ambil-perintah)
* [Struktur Folder Proyek](#-struktur-folder-proyek)
* [Panduan Kontribusi](#-panduan-kontribusi)
* [Lisensi](#-lisensi)
* [Info Penulis](#-info-penulis)
* [Kontak & Dukungan](#-kontak--dukungan)

---

## ✨ Fitur dan Fungsionalitas

Proyek ini terbagi menjadi dua komponen utama: **Dashboard Web** (backend) dan **Firmware ESP32** (klien hardware).

### 🖥️ Dashboard Web (Backend)

* **Manajemen Akses:** Tambah, edit, dan hapus kartu RFID yang diizinkan (CRUD).
* **Kontrol Iklim:**
    * Kontrol kipas secara manual (On/Off) dari jarak jauh.
    * Atur mode `auto` atau `manual`.
    * Sesuaikan ambang batas (threshold) suhu untuk mode otomatis.
* **Monitoring & Logging:**
    * Lihat data suhu (DHT22) dan kelembapan secara real-time.
    * Database log untuk semua aktivitas akses pintu (berhasil/gagal).
    * Database log untuk riwayat data sensor.
* **Manajemen Data:** Ekspor data log (akses, sensor) ke format CSV/Excel.
* **Autentikasi:** Sistem login yang aman untuk melindungi dashboard.

### 🔌 Firmware (ESP32)

* **Kontrol Akses Cerdas:** Menggunakan RFID reader MFRC522 dan Servo untuk membuka kunci pintu secara otomatis bagi kartu yang terdaftar.
* **Kontrol Iklim Otomatis:** Membaca sensor DHT22. Jika mode `auto` aktif, kipas (via Relay) akan menyala/mati secara otomatis berdasarkan ambang batas suhu yang diterima dari server.
* **Penyimpanan Permanen:** Menggunakan `Preferences.h` (pengganti EEPROM) untuk menyimpan pengaturan penting (mode kipas, threshold) sehingga aman saat listrik padam.
* **Tampilan Status:** LCD I2C 16x2 memberikan umpan balik instan (status koneksi, suhu, akses pintu).
* **Komunikasi Andal:**
    * Secara teratur mengirim data sensor (suhu, kelembapan) ke backend PHP.
    * Secara teratur *meminta* (polling) status perintah terbaru (mode kipas, threshold) dari backend.
    * *Catatan: Kode .ino yang disediakan menggunakan MQTT. Arsitektur ini juga dapat diadaptasi ke API HTTP (lihat [Contoh Penggunaan](#-contoh-penggunaan-api--cuplikan-kode)) untuk berinteraksi langsung dengan backend PHP.*

---

## 🛠️ Tumpukan Teknologi

### Backend & Frontend

* **PHP:** Bahasa scripting sisi server utama.
* **MySQL:** Database relasional untuk logging dan manajemen.
* **AdminLTE 3:** Template dashboard admin berbasis Bootstrap.
* **Bootstrap 4:** Kerangka kerja frontend CSS.
* **jQuery:** Pustaka JavaScript untuk manipulasi DOM.
* **Chart.js:** (Opsional, untuk visualisasi data log).
* **Composer:** Manajer dependensi PHP.

### Firmware (ESP32)

* **C++** (Arduino Framework)
* **Perangkat Keras:**
    * ESP32 (WROOM-32 atau serupa)
    * MFRC522 RFID Reader
    * Sensor Suhu & Kelembapan DHT22
    * Servo Motor (misalnya, SG90)
    * Modul Relay 5V
    * LCD 16x2 dengan modul I2C
    * Kipas mini 12V
* **Pustaka Kunci (Arduino):**
    * `WiFi.h` & `HTTPClient.h` (Untuk komunikasi API)
    * `MQTT.h` atau* `MQTTClient.h` (Jika menggunakan broker MQTT)
    * `MFRC522.h`
    * `ESP32Servo.h`
    * `DHT.h`
    * `ArduinoJson.h`
    * `Preferences.h`
    * `LiquidCrystal_I2C.h`

---

## 🚀 Panduan Instalasi

### Backend (Web Server)

1.  **Clone Repositori:**
    ```bash
    git clone [https://github.com/NAMA_ANDA/NAMA_REPO_ANDA.git](https://github.com/NAMA_ANDA/NAMA_REPO_ANDA.git)
    cd NAMA_REPO_ANDA
    ```

2.  **Install Dependensi PHP:**
    ```bash
    composer install
    ```

3.  **Setup Database:**
    * Buat database baru di MySQL (misalnya, `smarthome_db`).
    * Impor skema database: `config/database.sql`.

4.  **Konfigurasi:**
    * Salin file konfigurasi contoh:
        ```bash
        cp config/config.example.php config/config.php
        ```
    * Edit `config/config.php` dan masukkan kredensial database (host, user, pass, dbname) Anda.

5.  **Web Server:**
    * Arahkan *document root* Apache/Nginx Anda ke folder proyek ini.
    * Pastikan `mod_rewrite` (atau yang setara) diaktifkan.

### Firmware (ESP32)

1.  **Buka Proyek:**
    * Buka file `.ino` (misalnya, `ESP32_KIPAS_UPDATED.ino`) menggunakan Arduino IDE atau PlatformIO.

2.  **Install Pustaka:**
    * Melalui Library Manager, install:
        * `MFRC522` (by GithubCommunity)
        * `MQTT` (by 256dpi)
        * `ESP32Servo`
        * `DHT sensor library` (by Adafruit)
        * `Adafruit Unified Sensor`
        * `ArduinoJson` (by Benoit Blanchon)
        * `LiquidCrystal_I2C`

3.  **Konfigurasi Kredensial:**
    * Di dalam file `.ino`, sesuaikan variabel global berikut:
    ```cpp
    // ================= WiFi & MQTT =================
    const char ssid[] = "NAMA_WIFI_ANDA";
    const char pass[] = "PASSWORD_WIFI_ANDA";
    
    // Serial number unik untuk perangkat ini
    const String serial_number = "12345678"; 

    // Kredensial untuk MQTT Broker Anda (cth: shiftr.io)
    const char *mqtt_server   = "broker.anda.com";
    const char *mqtt_username = "username_mqtt";
    const char *mqtt_password = "password_mqtt";
    ```

4.  **Sesuaikan Pin:**
    * Pastikan pin yang didefinisikan di kode sesuai dengan wiring ESP32 Anda.
    ```cpp
    const int pinServo = 5;
    const int pinRFID_SDA = 15;
    const int pinRFID_RST = 27;
    const uint8_t DHTPIN = 4;
    const int pinRelay = 14;
    ```

5.  **Compile & Upload:**
    * Pilih board (misal: "ESP32 Dev Module") dan Port yang benar, lalu upload.

---

## 🔌 Contoh Penggunaan (API)

Kode `.ino` yang disediakan menggunakan **MQTT** sebagai protokol komunikasi. Dashboard PHP/MySQL Anda dapat diintegrasikan dengan broker MQTT (menggunakan pustaka seperti `php-mqtt/client`) atau firmware ESP32 dapat dimodifikasi untuk menggunakan **HTTP** secara langsung.

### 1. Model MQTT (Sesuai .ino)

ESP32 ini berkomunikasi penuh melalui topik MQTT. Anda dapat menggunakan dashboard apa pun (Node-RED, MQTT Explorer, atau dashboard web Anda yang terhubung ke MQTT) untuk mengontrolnya.

**Topik yang Di-subscribe (Control ESP32):**
*(Ganti `{serial_number}` dengan ID unik Anda)*

| Topik | Payload Contoh | Deskripsi |
| :--- | :--- | :--- |
| `smarthome/{serial_number}/servo` | `90` | Mengatur posisi servo (0-180). |
| `smarthome/{serial_number}/kipas/control` | `on` atau `off` | Kontrol manual kipas. |
| `smarthome/{serial_number}/kipas/mode` | `auto` atau `manual` | Mengubah mode operasi kipas. |
| `smarthome/{serial_number}/kipas/threshold` | `{"on": 35.5, "off": 30.0}` | Set threshold JSON (float). |
| `smarthome/{serial_number}/rfid/register` | `A1B2C3D4` | Mendaftarkan UID kartu baru. |
| `smarthome/{serial_number}/rfid/remove` | `A1B2C3D4` | Menghapus UID kartu. |

**Topik yang Di-publish (Status dari ESP32):**

| Topik | Payload Contoh | Deskripsi |
| :--- | :--- | :--- |
| `smarthome/status/{serial_number}` | `online` | Status koneksi (LWT: `offline`). |
| `smarthome/{serial_number}/dht/temperature` | `31.20` | Data suhu (float). |
| `smarthome/{serial_number}/dht/humidity` | `58.40` | Data kelembapan (float). |
| `smarthome/{serial_number}/pintu/status` | `tertutup` | Status kunci pintu (servo). |
| `smarthome/{serial_number}/kipas/status` | `on` | Status relay kipas. |
| `smarthome/{serial_number}/rfid/access` | `{"status":"granted"}` | Hasil scan RFID. |
| `smarthome/{serial_number}/rfid/info` | `{"action":"add","result":"ok"}`| Umpan balik registrasi kartu. |

### 2. Model API HTTP (Alternatif untuk Backend PHP)

Jika Anda ingin ESP32 berkomunikasi *langsung* dengan API PHP yang ada di repositori:

#### ESP32 -> Server: Kirim Data Sensor
Anda akan mengganti `readDHT()` untuk melakukan HTTP POST ke `api/receive_data.php`.

```cpp
#include <HTTPClient.h>
#include <ArduinoJson.h>

void sendSensorData(float t, float h) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin("[http://server-anda.com/api/receive_data.php](http://server-anda.com/api/receive_data.php)");
    http.addHeader("Content-Type", "application/json");

    JsonDocument doc;
    doc["serial_number"] = serial_number;
    doc["temperature"] = t;
    doc["humidity"] = h;

    String requestBody;
    serializeJson(doc, requestBody);

    int httpResponseCode = http.POST(requestBody);
    Serial.printf("HTTP POST Response: %d\n", httpResponseCode);
    http.end();
  }
}
````

#### ESP32 \<- Server: Ambil Perintah

Anda perlu fungsi di `loop()` untuk mem-polling `api/kipas_crud.php?action=get_status`.

```cpp
void checkFanStatus() {
  HTTPClient http;
  String url = "[http://server-anda.com/api/kipas_crud.php?action=get_status&serial=](http://server-anda.com/api/kipas_crud.php?action=get_status&serial=)" + serial_number;
  http.begin(url);
  
  int httpResponseCode = http.GET();
  
  if (httpResponseCode == 200) {
    String payload = http.getString();
    JsonDocument doc;
    deserializeJson(doc, payload);

    kipasMode = doc["mode"].as<String>();
    tempThresholdOn = doc["threshold_on"].as<float>();
    // ... update kipas sesuai data
  }
  http.end();
}
```

-----

## 📂 Struktur Folder Proyek

```
.
├── api/                # Endpoint API (CRUD, penerima data)
│   ├── config_crud.php
│   ├── dht_log.php
│   ├── kipas_crud.php
│   ├── receive_data.php
│   └── rfid_crud.php
├── assets/             # Aset frontend (CSS/JS kustom)
├── config/             # Konfigurasi backend dan DB
│   ├── config.example.php
│   ├── config.php
│   └── database.sql    # Skema Database MySQL
├── dist/               # Aset AdminLTE (CSS, JS, images)
├── export/             # (Opsional) Tempat penyimpanan file ekspor
├── vendor/             # Dependensi Composer
├── ESP32_KIPAS_UPDATED.ino # Kode firmware ESP32
├── index.php           # Halaman Dashboard Utama
├── kipas.php           # Halaman Manajemen Kipas
├── log.php             # Halaman Log Data
├── login.php           # Halaman Login
├── logout.php
├── rfid.php            # Halaman Manajemen RFID
└── README.md           # Anda di sini!
```

-----

## 🤝 Panduan Kontribusi

Kami menyambut baik kontribusi\! Jika Anda ingin meningkatkan proyek ini, silakan *fork* repositori ini dan ajukan *Pull Request* (PR).

1.  Fork repositori.
2.  Buat branch fitur baru (`git checkout -b fitur/NamaFitur`).
3.  Commit perubahan Anda (`git commit -m 'Menambahkan fitur A'`).
4.  Push ke branch (`git push origin fitur/NamaFitur`).
5.  Buka Pull Request.

-----

## 📜 Lisensi

Proyek ini dilisensikan di bawah **MIT License**. Lihat file `LICENSE` (jika ada) untuk detail lebih lanjut.

-----

## 👨‍💻 Info Penulis

  * **Pengelola Proyek:** [Nama Anda / Username GitHub Anda]
  * **Email:** [email@anda.com]
  * **GitHub:** [https://github.com/NAMA\_ANDA](https://www.google.com/search?q=https://github.com/NAMA_ANDA)

-----

## 📞 Kontak & Dukungan

Jika Anda menemukan bug atau memiliki pertanyaan, silakan buka **Issue** di repositori GitHub ini.

```
```
