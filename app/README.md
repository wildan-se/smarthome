Ringkasan:

Folder ini berisi skeleton aplikasi PHP untuk dashboard IoT (AdminLTE) menggunakan PHP 8.1 dan MySQL.

Langkah cepat:
1. Pastikan DocumentRoot server menunjuk ke `C:/laragon/www/smarthome/app/public`.
2. Edit `C:/laragon/www/smarthome/app/src/config.php` dengan kredensial database Anda dan pengaturan MQTT.
3. Jalankan `php C:/laragon/www/smarthome/app/setup.php` sekali untuk membuat tabel dan membuat akun admin awal.
4. Buka `http://localhost/` (atau VirtualHost) untuk membuka halaman login.

Catatan:
- MQTT WebSocket URL harus diisi sesuai broker Anda. Default diisi berdasarkan informasi yang Anda kirim.
- File ini hanya skeleton awal. Lanjutkan implementasi fitur (Chart.js, export, role management) setelah validasi koneksi.
