# Test Plan — Landing Pendaftaran Pasien (PR #1)

Repo: `kangsotox991/pasien` · Branch: `devin/1777175617-initial-app`
URL under test: `http://127.0.0.1:8080/` (PHP built-in server)

## What changed (user-visible)

- New PHP landing page dengan hero, form pendaftaran pasien (Tanggal, Nama, No. Registrasi, Diagnosa) dan riwayat pasien.
- Penyimpanan ke `data/patients.json` + `data/patients.csv`.
- Validasi **No. Registrasi hanya angka** (server-side regex + client-side `oninput` strip + HTML5 `pattern`).
- Mobile-first: form 1 kolom & daftar berbentuk **kartu** di mobile (<768px), **tabel** di desktop (≥768px).
- Toast hijau setelah submit sukses.

## Code references

- Form & validasi client: `public/index.php:281-293`
- Validasi server No. Registrasi: `src/validation.php:34-42`
- Storage: `src/storage.php:44-92`
- Toast flash: `public/index.php:177-184`
- Daftar mobile (kartu) vs desktop (tabel): `public/index.php:374-417`

## Pre-conditions (sudah disiapkan, BUKAN bagian rekaman)

- PHP 8.1 server jalan di `127.0.0.1:8080`, document root `public/`.
- File `data/patients.json` & `data/patients.csv` dibersihkan (state kosong).
- Verifikasi: HTTP 200, halaman menampilkan badge "0 entri" dan empty state "Belum ada data pasien".

## Tests

### T1 — Halaman awal (smoke)
**Aksi:** Buka `http://127.0.0.1:8080/` di Chrome 1280×800.
**Lulus jika:**
- Header menampilkan "Klinik Sehat Sentosa" + tombol "+ Pasien Baru".
- Hero menampilkan h1 "Catat data pasien cepat & rapi.".
- Stats grid menunjukkan **Total = 0**, **Hari ini = 0**, **Sheets = Off**.
- Section "Riwayat Pendaftaran" menampilkan badge "**0 entri**" dan kartu kosong "Belum ada data pasien".

### T2 — Submit data valid
**Aksi:** Pada form di hero, isi:
- Tanggal: `2026-04-26`
- Nama: `Andi Saputra`
- No. Registrasi: `20260001`
- Diagnosa: `Demam berdarah hari ke-3`

Klik tombol "Simpan Data Pasien".

**Lulus jika:**
- Halaman reload (URL berakhir `#daftar`).
- Toast hijau muncul dengan teks **persis**: "Data pasien berhasil disimpan."
- Tabel di section Riwayat menampilkan 1 baris dengan: tanggal "26 April 2026", nama "Andi Saputra", badge "20260001", diagnosa "Demam berdarah hari ke-3".
- Stats: **Total = 1**, **Hari ini = 1**, badge "**1 entri**".
- File `data/patients.json` berisi 1 record dengan `no_registrasi="20260001"` (verifikasi via shell — tidak masuk rekaman).

### T3 — Validasi No. Registrasi hanya angka (adversarial)
Test ini dirancang supaya jika regex `^\d+$` di server **atau** filter `oninput` di client dihilangkan, hasilnya berbeda secara visual.

**T3a — Client-side strip (oninput):**
**Aksi:** Pada field "No. Registrasi", ketik **persis** `ABC123def456`.
**Lulus jika:** Setelah selesai mengetik, isi field hanya berisi digit `123456` (huruf otomatis dihapus saat diketik).
> Jika filter rusak, field akan menampilkan `ABC123def456`.

**T3b — Server-side rejection (dengan JS dimatikan):**
Untuk membuktikan server juga memvalidasi (bukan hanya bergantung pada client filter `oninput`), matikan JavaScript di tab ini lewat DevTools, reload halaman, lalu submit form dengan No. Registrasi berisi huruf. Karena JS dimatikan, filter `oninput` & validasi HTML5 `pattern` tidak aktif — yang tersisa hanyalah validasi server.

**Aksi:**
1. Buka DevTools (Ctrl+Shift+I).
2. Buka Command Menu (Ctrl+Shift+P), ketik "Disable JavaScript", pilih "Disable JavaScript".
3. Tutup DevTools, reload halaman (Ctrl+R).
4. Isi form: Nama=`Bypass Test`, No. Registrasi=`REG-9999`, Diagnosa=`uji bypass`. Tanggal sudah default hari ini.
5. Klik "Simpan Data Pasien".

**Lulus jika:**
- Halaman re-render dengan form (bukan redirect ke `#daftar`).
- Pesan error merah muncul di bawah field No. Registrasi dengan teks **persis**: "No. registrasi hanya boleh berisi angka."
- File `data/patients.json` masih hanya berisi 1 record (record T2), **tidak** bertambah jadi 2.
- Stats card "Total" tetap menampilkan **1** (bukan 2).

### T4 — Mobile viewport
**Aksi:** Buka halaman ulang (data dari T2 masih ada) dengan viewport mobile 390×844 (iPhone-like).
**Lulus jika:**
- Header pada mobile menampilkan tombol "+ Pasien" (versi pendek), bukan "+ Pasien Baru".
- H1 hero membungkus dengan benar (tidak terpotong di kanan).
- Form berada di **bawah** hero (1 kolom), bukan di sebelah kanan.
- Section "Riwayat Pendaftaran" menampilkan **kartu** untuk record Andi Saputra (bukan tabel header "TANGGAL NAMA NO. REGISTRASI DIAGNOSA").

## Out of scope

- Integrasi Google Sheets (memerlukan Apps Script Web App eksternal — env `GSHEET_WEBHOOK_URL` kosong, jadi label "Sheets: Off" benar dan path itu tidak diuji).
- CI: belum ada konfigurasi CI di repo.
