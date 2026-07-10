# Menjalankan Project Internship Monitoring

Dokumentasi ini menjelaskan cara menjalankan project dari awal setelah clone repository dari GitHub.

Project ini memakai:

- Laravel untuk aplikasi dashboard
- Vite untuk frontend assets
- Python FastAPI untuk face-service / Face ID
- Database sesuai konfigurasi `.env`

## 1. Prasyarat

Pastikan sudah terinstall:

- PHP 8.3 atau lebih baru
- Composer
- Node.js dan npm
- Python 3.10 atau lebih baru
- MySQL/MariaDB atau database lain sesuai `.env`
- Git

Cek versi:

```bash
php -v
composer -V
node -v
npm -v
python --version
git --version
```

## 2. Clone Repository

```bash
git clone <url-repository>
cd internship-monitoring
```

## 3. Install Dependency Laravel

```bash
composer install
```

Command ini akan membuat ulang folder `vendor/`.

## 4. Setup Environment

Copy file environment:

```bash
cp .env.example .env
```

Untuk Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Generate app key:

```bash
php artisan key:generate
```

Edit file `.env`, lalu sesuaikan database:

```env
APP_NAME="Internship Monitoring"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=internship_monitoring
DB_USERNAME=root
DB_PASSWORD=
```

Buat database:

```sql
CREATE DATABASE internship_monitoring;
```

## 5. Migration dan Seeder

```bash
php artisan migrate
php artisan db:seed
```

Jika ingin reset database dari awal:

```bash
php artisan migrate:fresh --seed
```

## 6. Link Storage

Jalankan agar file upload seperti foto profile bisa diakses:

```bash
php artisan storage:link
```

## 7. Install Dependency Frontend

```bash
npm install
```

Command ini akan membuat ulang folder `node_modules/`.

## 8. Setup Face Service

Face service berada di folder `face-service`.

Buat virtual environment:

```bash
python -m venv face-service/.venv
```

Aktifkan virtual environment.

Windows PowerShell:

```powershell
.\face-service\.venv\Scripts\Activate.ps1
```

Windows CMD:

```cmd
face-service\.venv\Scripts\activate.bat
```

Linux/macOS:

```bash
source face-service/.venv/bin/activate
```

Install dependency Python:

```bash
python -m pip install --upgrade pip setuptools wheel
python -m pip install -r face-service/requirements.txt
```

Catatan: InsightFace dapat mendownload model `buffalo_l` saat pertama kali dijalankan, jadi koneksi internet mungkin diperlukan.

## 9. Konfigurasi Face Service

Pastikan `.env` berisi:

```env
FACE_SERVICE_URL=http://127.0.0.1:9000
FACE_SERVICE_TIMEOUT=45
```

Jika nama key di `config/services.php` berbeda, ikuti nama key yang dipakai di project.

## 10. Menjalankan Project

### Opsi A: Jalankan Semua Service Sekaligus

```bash
npm run dev:all
```

Script ini menjalankan:

- Laravel di `http://127.0.0.1:8000`
- Vite dev server
- Face service di `http://127.0.0.1:9000`

### Opsi B: Jalankan Manual

Terminal 1, Laravel:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2, Vite:

```bash
npm run dev
```

Terminal 3, Face service:

```bash
npm run face
```

Atau langsung:

```powershell
.\face-service\.venv\Scripts\python.exe -m uvicorn app.main:app --app-dir face-service --host 127.0.0.1 --port 9000
```

## 11. Akses Aplikasi

Buka aplikasi:

```text
http://127.0.0.1:8000
```

Cek face service:

```text
http://127.0.0.1:9000/health
```

Response normal:

```json
{
    "status": "ok",
    "algorithm": "insightface-buffalo_l-v1",
    "model": "buffalo_l"
}
```

## 12. Build Production Assets

```bash
npm run build
```

Jika Windows PowerShell memblokir `npm.ps1`, gunakan:

```powershell
npm.cmd run build
```

## 13. Testing

Jalankan semua test:

```bash
php artisan test
```

Jalankan test absensi saja:

```bash
php artisan test tests/Feature/AttendanceTest.php
```

## 14. Tambahan untuk Server Nginx yang Sudah Menjalankan PHP

Jika project Laravel/PHP sudah berjalan di server Nginx, maka tidak perlu mengubah alur Nginx besar-besaran. Tambahan Python hanya dijalankan sebagai service lokal di server, lalu Laravel memanggil service tersebut melalui `FACE_SERVICE_URL`.

Alurnya:

```text
Browser -> Nginx -> Laravel/PHP-FPM -> Python Face Service 127.0.0.1:9000
```

Python face service tidak perlu dibuka ke publik. Biarkan hanya listen di `127.0.0.1:9000`.

### 14.1 Masuk ke Folder Project di Server

Sesuaikan path dengan server. Contoh:

```bash
cd /var/www/internship-monitoring
```

Pastikan folder ini ada:

```bash
ls face-service
```

### 14.2 Install Kebutuhan Python di Server

Ubuntu/Debian:

```bash
sudo apt update
sudo apt install -y python3 python3-venv python3-pip build-essential cmake
```

### 14.3 Install Dependency Face Service

```bash
python3 -m venv face-service/.venv
face-service/.venv/bin/python -m pip install --upgrade pip setuptools wheel
face-service/.venv/bin/python -m pip install -r face-service/requirements.txt
```

### 14.4 Test Face Service Manual

Jalankan:

```bash
face-service/.venv/bin/python -m uvicorn app.main:app --app-dir face-service --host 127.0.0.1 --port 9000
```

Buka terminal lain, lalu test:

```bash
curl http://127.0.0.1:9000/health
```

Response normal:

```json
{
    "status": "ok",
    "algorithm": "insightface-buffalo_l-v1",
    "model": "buffalo_l"
}
```

Jika sudah normal, hentikan proses manual dengan `Ctrl+C`.

### 14.5 Set `.env` Laravel di Server

Edit `.env`:

```bash
nano .env
```

Pastikan ada:

```env
FACE_SERVICE_URL=http://127.0.0.1:9000
FACE_SERVICE_TIMEOUT=45
```

Refresh config Laravel:

```bash
php artisan config:clear
php artisan config:cache
```

### 14.6 Jadikan Face Service Otomatis Jalan dengan systemd

Buat folder HOME/cache model untuk InsightFace:

```bash
sudo mkdir -p /var/www/internship-monitoring/storage/face-service
sudo chown -R www-data:www-data /var/www/internship-monitoring/storage/face-service
```

Buat service:

```bash
sudo nano /etc/systemd/system/kmi-face-service.service
```

Isi file service. Sesuaikan `WorkingDirectory`, `Environment`, dan `ExecStart` jika path project berbeda.

```ini
[Unit]
Description=KMI Attendance Face Service
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/internship-monitoring
Environment=HOME=/var/www/internship-monitoring/storage/face-service
ExecStart=/var/www/internship-monitoring/face-service/.venv/bin/python -m uvicorn app.main:app --app-dir face-service --host 127.0.0.1 --port 9000
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Aktifkan service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable kmi-face-service
sudo systemctl start kmi-face-service
sudo systemctl status kmi-face-service
```

Cek log jika error:

```bash
sudo journalctl -u kmi-face-service -f
```

Test lagi:

```bash
curl http://127.0.0.1:9000/health
```

### 14.7 Nginx Perlu Diubah Apa?

Biasanya tidak perlu mengubah routing Nginx karena Python dipanggil oleh Laravel dari internal server.

Yang mungkin perlu ditambahkan hanya batas ukuran request karena gambar Face ID dikirim sebagai base64. Tambahkan di server block Nginx Laravel:

```nginx
client_max_body_size 20M;
```

Contoh:

```nginx
server {
    server_name domain-kamu.com;
    root /var/www/internship-monitoring/public;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

Reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

### 14.8 Command Setelah Update Code di Server

Jika ada update code dari Git:

```bash
cd /var/www/internship-monitoring
git pull
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.3-fpm
sudo systemctl restart kmi-face-service
```

### 14.9 Checklist Production

- Laravel/PHP tetap lewat Nginx seperti sebelumnya.
- `FACE_SERVICE_URL` mengarah ke `http://127.0.0.1:9000`.
- Python face service berjalan sebagai systemd service.
- Port `9000` tidak dibuka ke publik.
- `curl http://127.0.0.1:9000/health` berhasil dari server.
- Nginx memiliki `client_max_body_size 20M`.

## 15. Folder yang Tidak Dipush

Folder/file berikut biasanya tidak ikut dipush ke GitHub:

```text
vendor/
node_modules/
face-service/.venv/
.env
storage/app/public/*
```

Folder dan file tersebut dibuat ulang lewat langkah instalasi di atas.

## 16. Troubleshooting

### Composer dependency belum ada

```bash
composer install
```

### Node dependency belum ada

```bash
npm install
```

### Face service belum aktif

```bash
npm run face
```

Atau:

```bash
npm run dev:all
```

### Kamera atau Face ID gagal

Pastikan:

- Browser mengizinkan akses kamera
- Face service aktif di port `9000`
- `.env` mengarah ke `FACE_SERVICE_URL=http://127.0.0.1:9000`
- Hanya satu wajah di kamera
- Wajah terlihat jelas dan pencahayaan cukup

### Foto profile tidak muncul

```bash
php artisan storage:link
```

### Database error

Pastikan database sudah dibuat dan `.env` sudah benar, lalu jalankan:

```bash
php artisan migrate:fresh --seed
```

## 17. Ringkasan Command Cepat

Windows:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm install
python -m venv face-service/.venv
.\face-service\.venv\Scripts\python.exe -m pip install --upgrade pip setuptools wheel
.\face-service\.venv\Scripts\python.exe -m pip install -r face-service/requirements.txt
npm run dev:all
```

Linux/macOS:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm install
python -m venv face-service/.venv
source face-service/.venv/bin/activate
python -m pip install --upgrade pip setuptools wheel
python -m pip install -r face-service/requirements.txt
npm run dev:all
```
