# pasien

Halaman web pendaftaran pasien berbasis PHP. 1 folder, file-based storage (JSON + CSV), opsional sync ke Google Sheets.

## Struktur

```
.
├── index.php          # halaman utama (form + daftar + edit/hapus + download .txt)
├── lib/               # helper (config, storage, validation, gsheet) — diblokir dari URL via .htaccess
├── data/              # patients.json + patients.csv (auto-create) — diblokir dari URL via .htaccess
└── apps_script/       # opsional: webhook Google Sheets (Apps Script)
```

## Jalan lokal (PHP built-in server)

```bash
php -S 0.0.0.0:8080 -t .
```
Buka <http://localhost:8080>.

## Deploy ke shared hosting (cPanel / InfinityFree)

Upload **seluruh folder ini** ke `htdocs/` (atau subfolder di dalamnya). Tidak ada langkah tambahan; `data/` akan dibuat otomatis saat record pertama disimpan. `.htaccess` di `lib/` & `data/` mengunci akses langsung dari URL.

## Variabel environment opsional

- `APP_NAME`, `APP_TAGLINE` — branding.
- `GSHEET_WEBHOOK_URL` — URL deploy Apps Script untuk sync ke Google Sheets.
- `GSHEET_WEBHOOK_TOKEN` — shared secret yang dicek di Apps Script.
