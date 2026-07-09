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
FACE_RECOGNITION_URL=http://127.0.0.1:9000
FACE_RECOGNITION_TIMEOUT=45
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

## 14. Folder yang Tidak Dipush

Folder/file berikut biasanya tidak ikut dipush ke GitHub:

```text
vendor/
node_modules/
face-service/.venv/
.env
storage/app/public/*
```

Folder dan file tersebut dibuat ulang lewat langkah instalasi di atas.

## 15. Troubleshooting

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
- `.env` mengarah ke `FACE_RECOGNITION_URL=http://127.0.0.1:9000`
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

## 16. Ringkasan Command Cepat

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
