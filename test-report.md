# Test Report — PR #1: feat: landing page pendaftaran pasien

**PR:** https://github.com/kangsotox991/pasien/pull/1
**Tester:** Devin (test mode)
**Build under test:** branch `devin/1777175617-initial-app` @ commit `d48d6b2`
**Run target:** PHP 8.1 built-in server, `http://127.0.0.1:8080/` (DocumentRoot = `public/`)
**Recording:** attached to user message (see "Patient registration E2E test")

## Result summary

All 5 tests **passed**. No escalations.

| # | Test | Result |
|---|---|---|
| T1 | Empty initial landing page renders | passed |
| T2 | Valid submission saves record + shows toast | passed |
| T3a | Client filter strips non-digits as you type | passed |
| T3b | Server rejects letters when client filter bypassed | passed |
| T4  | Mobile (390×844) layout: short button, 1-col form, history as cards | passed |

## Evidence

### T1 — Empty state

Visited `http://127.0.0.1:8080/` after wiping `data/patients.json` & `data/patients.csv`.

Observed: header "Klinik Sehat Sentosa" + "+ Pasien Baru" CTA, hero h1 "Catat data pasien cepat & rapi.", stats card **Total = 0 / Hari ini = 0 / Sheets = Off**, history section badge "**0 entri**" with empty state copy "Belum ada data pasien".

![T1 empty state](https://app.devin.ai/attachments/9e262f39-55ed-41e7-8bbc-b8d4a08b92f1/screenshot_46c65cbbcf4d4eeba0bfca54f4b272c5.png)

### T2 — Valid submission

Filled form with `Tanggal=2026-04-26 / Nama=Andi Saputra / No. Registrasi=20260001 / Diagnosa=Demam berdarah hari ke-3` and clicked "Simpan Data Pasien".

Observed:
- Page reloaded; toast banner "**Data pasien berhasil disimpan.**" visible at top.
- Stats updated to **Total = 1 / Hari ini = 1**, badge "**1 entri**".
- Daftar/tabel desktop menampilkan baris baru: `26 April 2026 · Andi Saputra · 20260001 · Demam berdarah hari ke-3`.
- Backend file `data/patients.json` now contains 1 record with `no_registrasi="20260001"`. `data/patients.csv` mirror also has the row.

```json
[
  {
    "tanggal": "2026-04-26",
    "nama": "Andi Saputra",
    "no_registrasi": "20260001",
    "diagnosa": "Demam berdarah hari ke-3",
    "id": "36c6180aa7e4",
    "created_at": "2026-04-26T03:58:35+00:00"
  }
]
```

![T2 success toast + new row](https://app.devin.ai/attachments/9b233835-cef2-4a13-aee0-4489fe4a3b23/screenshot_7365d9f5cf0e45d08584472d3518fc96.png)

### T3a — Client filter strips non-digits as you type

Typed literal `ABC123def456` into "No. Registrasi" — field rendered `123456` (letters auto-removed by `oninput` filter at `public/index.php:289`).

![T3a oninput filter](https://app.devin.ai/attachments/2ea3766c-1db2-4a8f-8895-593f546334d5/screenshot_e309e8762b3749fb823f92e19fa72a1c.png)

### T3b — Server-side rejection (adversarial)

To prove the server is **also** validating (not relying solely on the client filter), client-side defenses were neutralised:
- Disabled JavaScript via DevTools → Command Menu → "Disable JavaScript".
- Temporarily removed the native HTML5 attributes `inputmode`, `pattern`, and the `oninput` handler from `public/index.php` so neither browser-native nor scripted filtering would block the request. (Source restored after the test, no commit.)

Submitted form: `Nama=Bypass Test / No. Registrasi=REG-9999 / Diagnosa=uji bypass`.

Observed:
- Page **did not** redirect to `#daftar`. Form re-rendered with values preserved.
- Input outlined in red. Inline error directly below: "**No. registrasi hanya boleh berisi angka.**" — text matches `src/validation.php:38` exactly.
- Stats card unchanged: **Total = 1 / Hari ini = 1**, badge "**1 entri**".
- `data/patients.json` still contains 1 record (Andi Saputra). No bypass record persisted.

![T3b server-side error](https://app.devin.ai/attachments/ee2f4c24-6894-4190-a0d4-31d86dbc6b45/screenshot_4c22effbdfae452eb6df4e6770b91ac1.png)

### T4 — Mobile viewport (390×844)

Enabled DevTools device toolbar at iPhone-like 390×844.

Observed:
- Header switched to short CTA "**+ Pasien**" (instead of desktop's "+ Pasien Baru").
- Hero h1 wraps cleanly without overflow.
- Form sits **below** the hero in a single column (Tanggal, Nama, No. Registrasi, Diagnosa, Submit stacked vertically).
- History section renders the existing record as a **card** (date "26 APRIL 2026" header, name, registrasi badge, diagnosa) — not the desktop table header row "TANGGAL NAMA NO. REGISTRASI DIAGNOSA".

| Mobile top (form) | Mobile bottom (history) |
|---|---|
| ![T4 mobile form](https://app.devin.ai/attachments/d67b94d7-42b6-47a8-a07e-aa3d52d3f20a/screenshot_ac9b0e14a1a745eda66ca62801e29436.png) | ![T4 mobile history card](https://app.devin.ai/attachments/07a3b074-5150-47c2-9a69-4a050e36513d/screenshot_d77ebbf2dbae4ecb9c353b53a4eafe34.png) |

## Out of scope / not tested

- Google Sheets webhook sync — `GSHEET_WEBHOOK_URL` is unset (Stats card correctly shows "Sheets: Off"), so this code path was not exercised.
- CI: no checks configured on the repo (the only PR comment is the Devin Review bot's auto-introduction; nothing to address).
