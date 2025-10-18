
# Sistem Smart Home Dashboard

Dashboard berbasis web untuk memonitor dan mengontrol perangkat keras rumah pintar, mencakup logging data sensor, manajemen akses pintu (RFID), dan kontrol relay/servo.

---

## 💡 Fitur Utama

* **Monitoring Sensor Real-time:** Menerima dan menampilkan data Suhu dan Kelembaban (dari sensor DHT, dll.) yang dikirim oleh perangkat keras.
* **Kontrol Perangkat:** Menyediakan antarmuka untuk mengontrol output (seperti relay untuk lampu/peralatan) dan mengendalikan Servo.
* **Sistem Keamanan RFID:** Manajemen Kartu RFID untuk kontrol akses pintu (tambah, edit, hapus kartu pengguna).
* **Logging Data Komprehensif:** Mencatat riwayat log dari pembacaan sensor (DHT) dan log akses pintu/RFID.
* **Export Data:** Fungsionalitas untuk mengekspor data log (kartu RFID, DHT, Pintu) ke format CSV.
* **Pengaturan Dinamis:** Mengelola konfigurasi sistem melalui antarmuka admin.
* **Pembersihan Log Otomatis:** Script untuk membersihkan log lama secara berkala agar database tetap optimal.

---

## 🛠️ Teknologi yang Digunakan

| Kategori | Teknologi | Detail |
| :--- | :--- | :--- |
| **Backend** | **PHP** (Native) | Menangani logika bisnis, API penerima data, dan CRUD. |
| **Database** | **MySQL/MariaDB** | Digunakan untuk menyimpan data sensor, log akses, dan konfigurasi. |
| **Frontend/UI** | **HTML, CSS, JavaScript** | Antarmuka pengguna responsif. |
| **Framework UI** | **AdminLTE v4** | Template dashboard untuk tampilan antarmuka yang profesional. |
| **Dependencies** | **Composer** | Digunakan untuk mengelola dependensi PHP. |

---

## ⚙️ Prasyarat Instalasi

Pastikan Anda telah menginstal perangkat lunak berikut:

1.  **Web Server:** Apache / Nginx (XAMPP, WAMP, atau sejenisnya direkomendasikan).
2.  **PHP:** Versi 7.4 atau lebih tinggi (disertai ekstensi `curl` dan `pdo_mysql`).
3.  **Database Server:** MySQL atau MariaDB.
4.  **Composer:** Manajer dependensi PHP.

### Langkah-langkah Instalasi

1.  **Clone Repositori:**
    ```bash
    git clone [URL_REPOSITORI_ANDA] smarthome
    cd smarthome
    ```

2.  **Konfigurasi PHP Dependencies:**
    ```bash
    composer install
    ```

3.  **Konfigurasi Database:**
    * Buat database baru (misalnya `smarthome_db`).
    * Impor skema database dari file `config/database.sql` ke database yang baru Anda buat.

4.  **Konfigurasi Aplikasi:**
    * Duplikat file `config/config.example.php` dan ganti namanya menjadi `config/config.php`.
    * Edit `config/config.php` dan sesuaikan pengaturan koneksi database serta kredensial login admin:
        ```php
        define('DB_SERVER', 'localhost');
        define('DB_USERNAME', 'root');
        define('DB_PASSWORD', '');
        define('DB_NAME', 'smarthome_db');
        // ... (dan ubah kredensial admin)
        define('ADMIN_USERNAME', 'admin');
        define('ADMIN_PASSWORD', 'password_aman'); // Ganti dengan hash password aman
        ```

5.  **Akses Aplikasi:**
    * Akses dashboard melalui *browser* Anda: `http://localhost/smarthome/`
    * Login menggunakan kredensial admin yang telah Anda atur.

---

## 🌳 Susunan Project

Struktur direktori utama project adalah sebagai berikut:

````

smarthome/
├── api/                  \# Endpoint untuk menerima data dari perangkat keras & CRUD
│   ├── receive\_data.php  \# API utama untuk logging sensor/perangkat
│   ├── rfid\_crud.php     \# Logika CRUD untuk kartu RFID
│   └── ...
├── assets/               \# Aset kustom (CSS, JS)
│   └── css/custom.css
├── config/               \# File konfigurasi sistem
│   ├── config.php        \# Pengaturan koneksi database & kredensial
│   └── database.sql      \# Skema database
├── dist/                 \# Asset AdminLTE yang sudah terkompilasi (CSS, JS, Fonts)
├── export/               \# Folder untuk menyimpan file CSV hasil export
├── vendor/               \# Dependensi PHP dari Composer
├── cleanup\_old\_logs.php  \# Script untuk menghapus data log lama
├── index.php             \# Halaman Dashboard utama
├── kontrol.php           \# Halaman kontrol perangkat (Relay, Servo)
├── log.php               \# Halaman log pembacaan sensor DHT
├── logs.php              \# Halaman log akses pintu/RFID
├── rfid.php              \# Halaman manajemen kartu RFID
├── settings.php          \# Halaman pengaturan sistem
└── ...

````

---

## 🚀 Contoh Penggunaan (Endpoint API)

Perangkat keras Anda (misalnya, ESP32 atau NodeMCU) dapat mengirim data sensor ke *dashboard* menggunakan permintaan **HTTP POST** ke endpoint yang sesuai.

### 1. Mengirim Data Sensor Suhu & Kelembaban (DHT)

Untuk mengirim data Suhu dan Kelembaban:

* **URL Endpoint:** `http://[IP_SERVER]/smarthome/api/receive_data.php`
* **Metode:** `POST`
* **Data (Form-Data atau JSON):**
    ```
    type: dht_log
    temperature: 28.5
    humidity: 75.2
    ```

### 2. Mengirim Data Log Akses Pintu (RFID)

Untuk mengirim log ketika kartu RFID di-scan:

* **URL Endpoint:** `http://[IP_SERVER]/smarthome/api/receive_data.php`
* **Metode:** `POST`
* **Data (Form-Data atau JSON):**
    ```
    type: door_log
    card_uid: 0A:1B:2C:3D
    status: ACCESS_GRANTED
    ```

---

## 🤝 Kontribusi

Kami sangat menyambut kontribusi dari komunitas! Jika Anda memiliki ide, laporan *bug*, atau perbaikan, silakan:

1.  *Fork* repositori ini.
2.  Buat *branch* baru: `git checkout -b feature/nama-fitur-baru`
3.  Lakukan perubahan Anda dan *commit* (*commit message* yang jelas).
4.  Dorong perubahan ke *branch* Anda: `git push origin feature/nama-fitur-baru`
5.  Buka **Pull Request** baru.

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah **Lisensi MIT**. Lihat file [LICENSE](LICENSE) (jika ada) untuk detail lebih lanjut, atau mengacu pada teks berikut:

````

MIT License

Copyright (c) 2024 [muhammad wildan septiano]

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

```
```
