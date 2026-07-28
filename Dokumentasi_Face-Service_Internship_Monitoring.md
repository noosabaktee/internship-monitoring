# Dokumentasi Face-Service (Internship Monitoring)

## Informasi Project

-   **Project**: Internship Monitoring

-   **Lokasi Project**:

    ``` text
    /home/iot_gateway/internship-monitoring/internship-monitoring
    ```

-   **Menjalankan Face Service**:

    ``` bash
    python -m uvicorn app.main:app --app-dir face-service --host 127.0.0.1 --port 9000
    ```

-   Menggunakan **Python Global** (bukan virtual environment).

-   Service dijalankan menggunakan **systemd** agar otomatis aktif saat
    boot dan restart jika crash.

------------------------------------------------------------------------

# A. Setup Awal

## 1. Masuk ke Folder Project

``` bash
cd /home/iot_gateway/internship-monitoring/internship-monitoring
```

## 2. Pastikan Python dan Uvicorn

``` bash
which python
python --version
python -m uvicorn --version
```

## 3. Tes Manual

``` bash
python -m uvicorn app.main:app --app-dir face-service --host 127.0.0.1 --port 9000
```

Jika berhasil, hentikan dengan:

``` text
Ctrl + C
```

------------------------------------------------------------------------

## 4. Buat Service systemd

Buat file:

``` bash
sudo nano /etc/systemd/system/face-service.service
```

Isi:

``` ini
[Unit]
Description=Face Service
After=network.target

[Service]
Type=simple
User=iot_gateway
Group=iot_gateway

WorkingDirectory=/home/iot_gateway/internship-monitoring/internship-monitoring

ExecStart=/usr/bin/python -m uvicorn app.main:app --app-dir face-service --host 127.0.0.1 --port 9000

Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

> Jika lokasi Python berbeda, ganti `/usr/bin/python` sesuai hasil
> `which python`.

Reload:

``` bash
sudo systemctl daemon-reload
```

Enable:

``` bash
sudo systemctl enable face-service
```

Start:

``` bash
sudo systemctl start face-service
```

Status:

``` bash
sudo systemctl status face-service
```

------------------------------------------------------------------------

# B. Menjalankan Jika Service Mati

## Cek Status

``` bash
sudo systemctl status face-service
```

## Jalankan

``` bash
sudo systemctl start face-service
```

## Restart

``` bash
sudo systemctl restart face-service
```

## Stop

``` bash
sudo systemctl stop face-service
```

## Reload Konfigurasi systemd

Jika file service berubah:

``` bash
sudo systemctl daemon-reload
sudo systemctl restart face-service
```

------------------------------------------------------------------------

# C. Melihat Log

Realtime:

``` bash
sudo journalctl -u face-service -f
```

100 log terakhir:

``` bash
sudo journalctl -u face-service -n 100 --no-pager
```

------------------------------------------------------------------------

# D. Verifikasi Port

``` bash
sudo ss -lntp | grep :9000
```

------------------------------------------------------------------------

# E. Jika Port 9000 Tidak Bisa Dipakai

Cari proses:

``` bash
sudo lsof -i :9000
```

atau

``` bash
sudo ss -lntp | grep :9000
```

------------------------------------------------------------------------

# F. Restart Nginx

Cek konfigurasi:

``` bash
sudo nginx -t
```

Reload:

``` bash
sudo systemctl reload nginx
```

Restart:

``` bash
sudo systemctl restart nginx
```

Status:

``` bash
sudo systemctl status nginx
```

------------------------------------------------------------------------

# G. Checklist Troubleshooting

1.  Cek status service.
2.  Cek log `journalctl`.
3.  Pastikan port 9000 tidak dipakai proses lain.
4.  Pastikan konfigurasi Nginx valid.
5.  Restart `face-service`.
6.  Jika masih gagal, jalankan manual:

``` bash
cd /home/iot_gateway/internship-monitoring/internship-monitoring
python -m uvicorn app.main:app --app-dir face-service --host 127.0.0.1 --port 9000
```

Jika manual berhasil tetapi service gagal, kemungkinan ada kesalahan
pada file `face-service.service`.
