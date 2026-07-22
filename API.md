# KMI Internship Monitoring API

Dokumentasi resmi REST API untuk aplikasi **KMI Internship Monitoring**. API ini disiapkan agar aplikasi web yang sudah ada dapat digunakan bersama aplikasi mobile tanpa mengakses database secara langsung.

> Status: **v1 aktif**  
> Base path: `/api/v1`  
> Format utama: `application/json`  
> Timezone bisnis: `Asia/Jakarta` (WIB)

## Daftar Isi

- [Memulai](#memulai)
- [Autentikasi](#autentikasi)
- [Format Respons](#format-respons)
- [Pagination, Filter, dan Format Data](#pagination-filter-dan-format-data)
- [Role dan Hak Akses](#role-dan-hak-akses)
- [Daftar Endpoint](#daftar-endpoint)
- [Dokumentasi Endpoint](#dokumentasi-endpoint)
- [Alur Mobile yang Direkomendasikan](#alur-mobile-yang-direkomendasikan)
- [Error dan HTTP Status](#error-dan-http-status)
- [Keamanan dan Privasi](#keamanan-dan-privasi)
- [Menjalankan API di Lokal](#menjalankan-api-di-lokal)

---

## Memulai

### Base URL

Pada lokal:

```text
http://127.0.0.1:8000/api/v1
```

Pada server, ganti origin sesuai nilai `APP_URL`:

```text
https://domain-anda.example/api/v1
```

### Header standar

Semua request yang membutuhkan login harus menyertakan:

```http
Accept: application/json
Authorization: Bearer <token>
```

Request JSON juga menggunakan:

```http
Content-Type: application/json
```

Untuk upload file, gunakan `multipart/form-data`; jangan set `Content-Type` secara manual di mobile client agar boundary multipart dibuat otomatis.

### Contoh request pertama

```bash
curl -X POST "http://127.0.0.1:8000/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "intern@example.com",
    "password": "password",
    "device_name": "Android Samsung A54"
  }'
```

Simpan `data.token` secara aman di secure storage perangkat. Jangan menyimpan token di log, analytics event, URL, atau local storage yang dapat dibaca aplikasi lain.

---

## Autentikasi

API menggunakan personal access token. Token dibuat saat login/register, dikirim sebagai `Bearer token`, dan disimpan di server dalam bentuk hash SHA-256. Nilai token plaintext hanya dikirim sekali pada response login/register.

### `POST /auth/login`

Login dengan akun pada tabel `mUser`.

Request JSON:

| Field | Tipe | Wajib | Keterangan |
|---|---|---:|---|
| `email` | string email | Ya | Email akun aktif |
| `password` | string | Ya | Password akun |
| `device_name` | string | Tidak | Nama perangkat, maksimal 100 karakter |

Response `200`:

```json
{
  "success": true,
  "message": "Login berhasil.",
  "data": {
    "token": "a1b2c3...",
    "token_type": "Bearer",
    "expires_at": "2026-08-14T10:00:00.000000Z",
    "user": {
      "id": 12,
      "email": "intern@example.com",
      "role": "Intern",
      "profile_photo": "profile-photos/avatar.jpg",
      "name": "Nama Intern",
      "intern": { "id": 4, "number": "INT-004", "name": "Nama Intern" },
      "mentor": null,
      "admin_profile": null
    }
  }
}
```

### `POST /auth/register`

Membuat akun baru sekaligus membuat profil berdasarkan role.

```json
{
  "name": "Nama Baru",
  "email": "baru@example.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "role": "Intern",
  "gender": "Male",
  "device_name": "Android App"
}
```

Nilai `role` yang valid: `Intern`, `Mentor`, `Headmaster`, `HRD`. Response sama seperti login, tetapi statusnya `201`.

> Untuk deployment publik, pendaftaran `Headmaster` dan `HRD` sebaiknya dibatasi atau dihapus dari UI mobile. Endpoint saat ini mengikuti perilaku registrasi web yang sudah ada.

### `GET /me`

Mengambil user dan profil yang terkait dengan token aktif.

### `POST /auth/logout`

Mencabut token yang sedang digunakan. Token langsung tidak dapat digunakan kembali.

### `POST /auth/logout-all`

Mencabut semua token milik user, termasuk token pada perangkat lain.

---

## Format Respons

### Respons sukses

```json
{
  "success": true,
  "message": "Pesan singkat untuk UI atau log aplikasi.",
  "data": {},
  "meta": {}
}
```

`meta` hanya dikirim untuk response yang membutuhkan metadata, terutama pagination.

### Respons error

```json
{
  "success": false,
  "message": "Data yang dikirim tidak valid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

`errors` berisi error per field. Error bisnis non-field biasanya memakai key seperti `attendance` atau `wfh`.

---

## Pagination, Filter, dan Format Data

Endpoint collection menggunakan query parameter berikut jika disebutkan pada endpoint:

| Parameter | Default | Batas | Keterangan |
|---|---:|---:|---|
| `page` | `1` | - | Nomor halaman |
| `per_page` | `20` | `100` | Jumlah item per halaman |
| `search` | - | - | Pencarian nama pada endpoint yang mendukung |
| `from` | - | `YYYY-MM-DD` | Batas tanggal awal |
| `to` | - | `YYYY-MM-DD` | Batas tanggal akhir |

Contoh response pagination:

```json
{
  "success": true,
  "message": "Daftar project berhasil diambil.",
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "last_page": 3,
    "total": 52,
    "from": 1,
    "to": 20
  }
}
```

Format umum:

- Tanggal: `YYYY-MM-DD`, contoh `2026-07-15`.
- Timestamp: ISO 8601, contoh `2026-07-15T08:30:00+07:00`.
- Jam operasional absensi mengikuti WIB.
- Nilai ID berbentuk integer.
- Nilai `null` berarti data memang tidak tersedia, bukan string kosong.
- Field `active` atau `is_read` dikirim sebagai boolean JSON (`true`/`false`).

---

## Role dan Hak Akses

| Role | Akses API utama |
|---|---|
| `Intern` | Profil sendiri, internship sendiri, project yang diikuti, evaluasi/achievement sendiri, absensi, WFH, notifikasi, calendar sharing, skill set |
| `Mentor` | Dashboard, project, daftar intern digitalisasi, evaluasi, achievement, leaderboard, calendar sharing, internship assignment sesuai relasi |
| `Headmaster` | Seluruh data operasional, master data, project handle, konfigurasi/lokasi absensi, review WFH |
| `HRD` | Dashboard, data intern/mentor, evaluasi, achievement, leaderboard, absensi, review WFH, master data |

API selalu melakukan pembatasan data di server. Mobile client tidak boleh menganggap menyembunyikan menu sebagai mekanisme keamanan.

---

## Daftar Endpoint

### Auth dan profil

| Method | Path | Auth | Keterangan |
|---|---|---|---|
| `POST` | `/auth/login` | Tidak | Login dan membuat token |
| `POST` | `/auth/register` | Tidak | Registrasi akun dan profil |
| `POST` | `/auth/logout` | Ya | Cabut token aktif |
| `POST` | `/auth/logout-all` | Ya | Cabut semua token user |
| `GET` | `/me` | Ya | User aktif |
| `GET` | `/profile` | Ya | Detail profil |
| `PATCH` | `/profile` | Ya | Update profil |
| `POST` | `/profile/photo` | Ya | Upload foto profil |
| `POST` | `/profile/face-enrollment` | Intern | Daftar Face ID |
| `DELETE` | `/profile/face-enrollment` | Intern | Nonaktifkan Face ID |

### Dashboard dan katalog

| Method | Path | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/dashboard` | Ya | KPI, project aktif, event, leaderboard ringkas |
| `GET` | `/projects` | Sesuai role | Daftar project aktif |
| `GET` | `/projects/{project}` | Sesuai role | Detail project, stages, assignment, mentor |
| `GET` | `/calendar-sharings` | Ya | Event calendar sharing |
| `GET` | `/skill-sets` | Ya | Master skill set aktif |
| `GET` | `/project-handles` | Headmaster | Bobot dan durasi project handle |
| `GET` | `/interns` | Mentor/admin | Daftar intern digitalisasi |
| `GET` | `/interns/{intern}` | Owner/mentor/admin | Detail intern |
| `GET` | `/mentors` | Ya | Daftar mentor aktif |
| `GET` | `/achievements` | Sesuai role | Achievement |
| `GET` | `/evaluations` | Sesuai role | Evaluasi |
| `GET` | `/leaderboard` | Sesuai role | Leaderboard dan bobot |

### Internship dan project pribadi

| Method | Path | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/me/internship` | Intern | Ringkasan internship |
| `GET` | `/me/projects` | Intern | Assignment project sendiri |
| `PATCH` | `/me/projects/{assignment}` | Owner/mentor/Headmaster | Update progress/status |
| `GET` | `/me/evaluations` | Intern | Evaluasi sendiri |
| `GET` | `/me/evaluations/{evaluation}/certificate` | Intern pemilik | Preview/download sertifikat yang sudah terbit |
| `GET` | `/me/achievements` | Intern | Achievement sendiri |

### Absensi

| Method | Path | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/attendance` | Intern/admin absensi | Riwayat dan status absensi |
| `POST` | `/attendance/check-in` | Intern | Clock in dengan Face ID + lokasi |
| `POST` | `/attendance/check-out` | Intern | Clock out dengan Face ID + lokasi |
| `GET` | `/attendance/locations` | HRD/Headmaster | Konfigurasi lokasi kantor |

### Work From Home

| Method | Path | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/work-from-home` | Intern/admin absensi | Daftar pengajuan |
| `POST` | `/work-from-home` | Intern | Membuat pengajuan |
| `POST` | `/work-from-home/{id}/approve` | HRD/Headmaster | Menyetujui pengajuan |
| `POST` | `/work-from-home/{id}/reject` | HRD/Headmaster | Menolak pengajuan |
| `POST` | `/work-from-home/{id}/cancel` | Owner/admin absensi | Membatalkan pengajuan pending |
| `GET` | `/work-from-home/{id}/attachment` | Owner/admin absensi | Download/preview lampiran |

### Notifikasi

| Method | Path | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/notifications` | Ya | Daftar notifikasi |
| `PATCH` | `/notifications/{id}/read` | Owner | Tandai satu notifikasi dibaca |
| `POST` | `/notifications/read-all` | Ya | Tandai seluruh notifikasi dibaca |

---

## Dokumentasi Endpoint

### Profil

#### `PATCH /profile`

Semua field bersifat opsional; hanya field yang dikirim yang diperbarui.

```json
{
  "name": "Nama yang diperbarui",
  "gender": "Male",
  "university": "Universitas Contoh",
  "department": "Teknik Informatika",
  "bio": "Bio singkat",
  "phone": "+628123456789"
}
```

`university`, `department`, dan `bio` digunakan pada profil intern. `phone` terutama digunakan pada profil admin.

#### `POST /profile/photo`

`multipart/form-data`:

| Field | Tipe | Keterangan |
|---|---|---|
| `photo` | file | JPG, JPEG, PNG, WEBP, maksimal 2 MB |

#### `POST /profile/face-enrollment`

Request JSON:

```json
{
  "images": [
    "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ...",
    "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ..."
  ]
}
```

Face service mengembalikan embedding dan metadata kualitas. Embedding tidak pernah dikirim dalam response API dan tidak boleh disimpan oleh aplikasi mobile.

### Dashboard, project, dan katalog

#### `GET /dashboard`

Mengembalikan:

- `total_interns`
- `active_projects`
- `average_exposure_score`
- `average_progress`
- `achievements`
- `project_type_counts`
- `upcoming_calendar_sharings`
- `leaderboard` ringkas

#### `GET /projects`

Filter yang tersedia:

```text
/projects?type=Main&skill_set_id=2&page=1&per_page=20
```

Item project memiliki bentuk ringkas berikut:

```json
{
  "id": 1,
  "name": "Digital Twin Project",
  "type": "Main",
  "skill_set": {
    "id": 2,
    "name": "Engineering Modeling & Simulation",
    "description": "..."
  },
  "start_date": "2026-06-01",
  "end_date": "2026-08-31",
  "description": "Deskripsi project",
  "active": true,
  "stages": [],
  "assignments": [],
  "mentors": []
}
```

#### `GET /calendar-sharings`

Filter:

```text
/calendar-sharings?from=2026-07-01&to=2026-07-31&status=Open
```

#### `GET /leaderboard`

Response `data` memiliki `weights` dan `items`. `score` dihitung dari jumlah assignment aktif per tipe project dikalikan bobot aktif:

```json
{
  "weights": {
    "main": 10,
    "collaboration": 6,
    "satellite": 2,
    "sharing": 4
  },
  "items": [
    {
      "rank": 1,
      "intern": {},
      "mentor": {},
      "main_project": "Digital Twin Project",
      "score": 16,
      "period": "Jul 2026",
      "breakdown": {
        "main": 1,
        "collaboration": 1,
        "satellite": 0,
        "sharing": 0
      }
    }
  ]
}
```

### Internship sendiri

#### `GET /me/internship`

Mengembalikan status `active` atau `completed`, rata-rata progress, jumlah project, evaluasi terakhir, achievement, dan jumlah pengajuan WFH.

#### `GET /me/projects`

Filter:

```text
/me/projects?active=true&page=1&per_page=20
```

#### `PATCH /me/projects/{assignment}`

Intern dapat memperbarui progress assignment miliknya. Mentor terkait atau Headmaster dapat memperbarui assignment yang menjadi tanggung jawabnya.

```json
{
  "progress": 75.5,
  "status": "On Progress"
}
```

`progress` harus berada pada rentang `0` sampai `100`.

#### `GET /me/evaluations/{evaluation}/certificate`

Response berupa PDF binary dengan autentikasi Bearer. Endpoint hanya dapat dibuka oleh intern pemilik setelah sertifikat diterbitkan; sebelum itu server mengembalikan `404`. Item `GET /me/evaluations` menyediakan `certificate_url` setelah publikasi, sementara nilai dan catatan evaluasi belum ditampilkan sebelum publikasi.

### Absensi mobile

#### `GET /attendance`

Filter:

```text
/attendance?from=2026-07-01&to=2026-07-31&page=1&per_page=20
```

Untuk admin absensi, `intern_id` dapat digunakan:

```text
/attendance?intern_id=4&from=2026-07-01&to=2026-07-31
```

Response menyediakan `today`, status Face ID, `work_mode`, aturan jam absensi, dan `records`. Data lokasi keluar sudah dipisahkan dari lokasi clock in.

#### `POST /attendance/check-in`

Request JSON:

```json
{
  "image": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ...",
  "latitude": -6.2000001,
  "longitude": 106.8166667,
  "accuracy": 12.5,
  "device": "Android Samsung A54"
}
```

Aturan yang diterapkan server:

1. Akun harus merupakan intern aktif.
2. Masa internship belum selesai.
3. Hari harus merupakan hari kerja Senin-Jumat.
4. Jam clock in harus berada pada window yang dikonfigurasi.
5. Face ID harus sudah terdaftar dan cocok dengan threshold server.
6. Untuk mode Office, koordinat harus berada dalam radius dan toleransi lokasi kantor.
7. Jika ada WFH Approved pada tanggal tersebut, mode otomatis menjadi `WFH` dan validasi radius kantor tidak digunakan.
8. Clock in kedua pada tanggal yang sama ditolak.

#### `POST /attendance/check-out`

Request sama dengan check in. Server memeriksa clock in hari tersebut, window clock out, Face ID, dan lokasi sesuai mode kerja.

Response absensi:

```json
{
  "success": true,
  "message": "Clock In berhasil dicatat.",
  "data": {
    "id": 91,
    "intern_id": 4,
    "user_id": 12,
    "date": "2026-07-15",
    "work_mode": "Office",
    "status": "Hadir",
    "clock_in": "2026-07-15T08:12:03+07:00",
    "clock_in_status": "Tepat Waktu",
    "clock_out": null,
    "clock_out_status": null,
    "location": {
      "name": "Kantor KMI",
      "url": "https://www.google.com/maps?q=-6.2,106.8166667",
      "latitude": -6.2000001,
      "longitude": 106.8166667,
      "accuracy": 12.5,
      "distance_meter": 18.4,
      "allowed_distance_meter": 75,
      "within_tolerance": true
    },
    "clock_out_location": {},
    "face": {
      "distance": 0.21,
      "algorithm": "insightface-buffalo_l-v1"
    },
    "clock_out_face": {},
    "note": "Clock In tepat waktu"
  }
}
```

`image` harus dikirim dalam format yang diterima face service, umumnya Data URI base64. Untuk produksi, batasi ukuran gambar dan lakukan kompresi di client sebelum upload.

### Work From Home

#### `POST /work-from-home`

Gunakan `multipart/form-data`:

| Field | Tipe | Wajib | Keterangan |
|---|---|---:|---|
| `start_date` | date | Ya | Tidak boleh sebelum hari ini |
| `end_date` | date | Ya | Tidak boleh sebelum `start_date` atau akhir internship |
| `reason` | string | Ya | Maksimal 1.500 karakter |
| `attachment` | file | Ya | PDF/JPG/JPEG/PNG/WEBP, maksimal 5 MB |

Pengajuan yang beririsan dengan pengajuan `Pending` atau `Approved` akan ditolak.

#### Review WFH

Approve:

```json
{
  "review_note": "WFH disetujui. Tetap ikuti agenda harian tim."
}
```

Catatan persetujuan bersifat opsional dan dapat diperbarui oleh HRD/Headmaster.

Reject wajib menyertakan catatan:

```json
{
  "review_note": "Mohon lampirkan kebutuhan WFH yang lebih detail."
}
```

#### `POST /work-from-home/{id}/cancel`

Tidak memerlukan body. Hanya status `Pending` yang dapat dibatalkan oleh pemilik atau admin absensi.

#### `GET /work-from-home/{id}/attachment`

Response berupa file binary dengan `Content-Disposition: inline`, bukan JSON envelope. Gunakan token yang sama pada header Authorization.

### Notifikasi

#### `GET /notifications`

Untuk hanya mengambil notifikasi yang belum dibaca:

```text
/notifications?filter=unread&page=1&per_page=20
```

Item notification:

```json
{
  "id": 31,
  "type": "wfh",
  "title": "Pengajuan WFH disetujui",
  "message": "Pengajuan WFH kamu disetujui.",
  "link": "/work-from-home",
  "read_at": null,
  "is_read": false,
  "created_at": "2026-07-15T08:30:00+07:00"
}
```

---

## Alur Mobile yang Direkomendasikan

### Bootstrap aplikasi

1. Login dengan email, password, dan nama perangkat.
2. Simpan token pada Keychain (iOS) atau EncryptedSharedPreferences/Keystore (Android).
3. Panggil `GET /me` dan `GET /dashboard`.
4. Simpan hanya data cache non-sensitif; token jangan masuk ke cache biasa.

### Halaman internship intern

1. `GET /me/internship` untuk kartu ringkasan.
2. `GET /me/projects` untuk daftar project.
3. `GET /me/evaluations` dan `GET /me/achievements` untuk riwayat.
4. Jika user mengedit progress, kirim `PATCH /me/projects/{assignment}` lalu gunakan response sebagai source of truth.

### Halaman absensi

1. `GET /attendance` saat halaman dibuka.
2. Jika `face_registered=false`, arahkan user ke `POST /profile/face-enrollment`.
3. Minta izin kamera dan lokasi secara eksplisit.
4. Kirim foto terbaru, latitude, longitude, accuracy, dan device ke endpoint check in/out.
5. Jika error `422`, tampilkan `message` dan field `errors.attendance`.
6. Setelah berhasil, gunakan `data` response untuk memperbarui tampilan tanpa menebak status dari jam lokal.

### Token kedaluwarsa

Jika menerima `401`:

1. Hapus token dari secure storage.
2. Arahkan user ke halaman login.
3. Jangan melakukan retry berulang tanpa batas.

---

## Error dan HTTP Status

| Status | Arti | Tindakan client |
|---:|---|---|
| `200` | Berhasil | Gunakan `data` |
| `201` | Resource berhasil dibuat | Gunakan `data` |
| `401` | Token tidak ada/tidak valid/kedaluwarsa atau kredensial salah | Login ulang |
| `403` | Token valid tetapi role tidak punya akses | Sembunyikan aksi tersebut; jangan retry |
| `404` | Resource tidak ditemukan | Tampilkan empty state atau not found |
| `409` | Konflik data, jika digunakan oleh integrasi lanjutan | Refresh data lalu ulangi dengan hati-hati |
| `422` | Validasi atau aturan bisnis gagal | Tampilkan `message` dan `errors` |
| `429` | Terlalu banyak request; login/register dibatasi | Tunggu sesuai `Retry-After` |
| `500` | Kesalahan server | Tampilkan pesan generik dan laporkan request ID jika tersedia |

Jangan menampilkan stack trace atau detail exception mentah kepada end user.

---

## Keamanan dan Privasi

- API menggunakan token Bearer; selalu gunakan HTTPS di staging/production.
- Password tidak pernah dikirim pada response.
- Face descriptor tidak pernah dikirim pada response dan tidak boleh dicache oleh client.
- Validasi radius lokasi, Face ID, window absensi, dan status internship dilakukan server-side.
- Gunakan secure storage untuk token.
- Hindari logging body request pada endpoint Face ID, absensi, dan upload WFH.
- Batasi permission kamera/lokasi hanya ketika fitur digunakan.
- Jika perangkat hilang, panggil `/auth/logout-all` dari perangkat lain atau cabut token langsung dari database.
- Untuk production, pertimbangkan menambahkan refresh-token rotation, device management, dan pembatasan registrasi role admin.

---

## Menjalankan API di Lokal

### 1. Konfigurasi environment

Salin `.env.example` menjadi `.env`, lalu pastikan koneksi database dan face service sesuai. Variabel API yang tersedia:

```dotenv
API_TOKEN_TTL_DAYS=30
FACE_SERVICE_URL=http://127.0.0.1:9000
FACE_SERVICE_TIMEOUT=45
```

### 2. Jalankan migration

Migration API token menambahkan tabel `api_tokens`.

```bash
php artisan migrate
```

### 3. Jalankan server

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Jika memakai fitur Face ID:

```bash
npm run face
```

Atau jalankan seluruh environment development sesuai `package.json`:

```bash
npm run dev:all
```

### 4. Verifikasi route

```bash
php artisan route:list --path=api
```

### 5. Smoke test

```bash
curl -i "http://127.0.0.1:8000/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"intern@example.com","password":"password","device_name":"curl"}'
```

---

## Catatan Implementasi

API ini membaca dan memanfaatkan domain model yang sudah tersedia di project: user/profile, intern, mentor, admin profile, project, project stage, project assignment, project mentor, skill set, project handle/weight, evaluation, achievement, calendar sharing, attendance setting/location/record, Face ID enrollment, WFH request, dan notification.

API versioning menggunakan prefix URL `/v1`. Perubahan breaking sebaiknya dibuat pada `/v2`; perubahan field tambahan yang backward-compatible dapat dirilis di `/v1` dengan tetap mempertahankan field lama.
