# Standard Penamaan Database KMI

Dokumen ini adalah referensi cepat untuk konvensi penamaan database, table, dan kolom yang digunakan di perusahaan KMI.

---

## 1. Standard Penamaan DATABASE

**Format:** `(Company)(App)(Dept)(Year)`

| Komponen | Nilai |
|----------|-------|
| Company  | KMI   |
| App      | POS   |
| Dept     | ENG   |
| Year     | 2021  |

**Contoh:** `KMIPOSENG2021`

---

## 2. Standard Penamaan TABLE

**Format:** `prefix(NamaTable)`

| Jenis Table        | Prefix | Format                | Contoh                          |
|--------------------|--------|-----------------------|---------------------------------|
| Master             | `m`    | `m(Table)`            | `mProduct`, `mEmployee`         |
| Transaction        | `tr`   | `tr(Table)`           | `trPenjualan`, `trPengembalian` |
| Transaction Detail | `tr`   | `tr(Table)_Details`   | `trPenjualan_Details`           |
| Log                | `log`  | `log(Table)`          | `logData`, `logHistory`         |
| Telemetry          | `dt`   | `dt(Table)`           | `dtAmpere`, `dtKwh`             |

---

## 3. Standard Penamaan KOLOM

**Format:** `(type)(table)(column)`

### Tipe Data yang Digunakan

| Prefix   | Tipe          | Keterangan                        |
|----------|---------------|-----------------------------------|
| `int`    | Integer       | Angka bulat                       |
| `txt`    | Text          | String, varchar                   |
| `dtm`    | DateTime      | Tanggal & waktu                   |
| `bit`    | Bit           | Nilai 0 atau 1                    |
| `float`  | Float         | Angka desimal                     |

### Catatan Penting
- Gunakan prefix `txt` untuk semua data yang berhubungan dengan text (string, varchar)
- Gunakan prefix `bit` hanya untuk nilai 0 dan 1

### Contoh Kolom — Table `mProduct`

| Kolom            | Tipe Data | Keterangan                      |
|------------------|-----------|---------------------------------|
| `intProduct_ID`  | int       | Primary Key                     |
| `txtProductName` | txt       | Nama Produk                     |
| `floatHarga`     | float     | Harga Produk                    |
| `txtInsertedBy`  | txt       | User yang membuat data          |
| `dtmInserted`    | dtm       | Tanggal & waktu dibuat          |
| `txtUpdatedBy`   | txt       | User yang terakhir update       |
| `dtmUpdated`     | dtm       | Tanggal & waktu terakhir update |
| `bitActive`      | bit       | Status aktif (1=Aktif, 0=Non Aktif) |

### Contoh Kolom Lain
- `intEmployee_ID`
- `txtEmployeeName`
- `dtmBirthDate`
- `bitIsActive`
- `intQuantity`
- `dtmTransactionDate`

---

## 4. Kolom Wajib Setiap Jenis Table

### MASTER (`mTable`)

| Kolom           | Keterangan                          |
|-----------------|-------------------------------------|
| `txtInsertedBy` | User yang membuat data              |
| `dtmInserted`   | Tanggal & waktu dibuat              |
| `txtUpdatedBy`  | User yang terakhir update           |
| `dtmUpdated`    | Tanggal & waktu terakhir update     |
| `bitActive`     | Status aktif (1=Aktif, 0=Non Aktif) |

### TRANSACTION (`trTable`)

| Kolom           | Keterangan             |
|-----------------|------------------------|
| `txtInsertedBy` | User yang membuat data |
| `dtmInserted`   | Tanggal & waktu dibuat |

### TRANSACTION DETAIL (`trTable_Details`)

| Kolom           | Keterangan             |
|-----------------|------------------------|
| `txtInsertedBy` | User yang membuat data |
| `dtmInserted`   | Tanggal & waktu dibuat |

### LOG (`logTable`)

| Kolom           | Keterangan                      |
|-----------------|---------------------------------|
| `txtInsertedBy` | User yang membuat data          |
| `dtmInserted`   | Tanggal & waktu dibuat          |
| `txtUpdatedBy`  | User yang terakhir update       |
| `dtmUpdated`    | Tanggal & waktu terakhir update |

### TELEMETRY (`dtTable`)

Struktur kolom mengikuti data dari perangkat IoT / sumber data (tidak ada kolom wajib standar, menyesuaikan sumber data).

---

## 5. Contoh Lengkap

**Database:** `KMIPOSENG2021`

| Jenis              | Nama Table            |
|--------------------|-----------------------|
| Master             | `mProduct`            |
| Transaction        | `trPenjualan`         |
| Transaction Detail | `trPenjualan_Details` |
| Log                | `logHistory`          |
| Telemetry          | `dtKwh`               |

### Kolom lengkap `mProduct`

```
intProduct_ID     → Primary Key (int)
txtProductName    → Nama produk (txt)
floatHarga        → Harga produk (float)
txtInsertedBy     → User pembuat (txt)
dtmInserted       → Waktu dibuat (dtm)
txtUpdatedBy      → User updater (txt)
dtmUpdated        → Waktu update (dtm)
bitActive         → Status aktif (bit)
```

---

## Quick Reference

> Saat membuat table baru, selalu tanya:
> 1. **Jenis table apa?** → tentukan prefix (`m`, `tr`, `log`, `dt`)
> 2. **Kolom wajib sudah ada?** → cek section 4 sesuai jenis table
> 3. **Penamaan kolom sudah pakai format `(type)(table)(column)`?** → pastikan prefix tipe data benar
> 4. **Database sudah pakai format `(Company)(App)(Dept)(Year)`?** → contoh: `KMIPOSENG2021`
