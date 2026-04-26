/**
 * Google Apps Script Web App untuk menerima data pasien dari aplikasi PHP
 * dan menambahkannya sebagai baris baru pada Google Sheet.
 *
 * Cara pakai:
 *   1. Buka Google Sheet yang ingin dipakai sebagai database.
 *   2. Menu: Extensions -> Apps Script.
 *   3. Hapus isi `Code.gs`, tempel seluruh isi file ini.
 *   4. (Opsional) Ganti SHARED_TOKEN dengan string acak. Set nilai yang sama
 *      pada env GSHEET_WEBHOOK_TOKEN di server PHP supaya request lain ditolak.
 *   5. Klik Deploy -> New deployment.
 *        - Type: Web app
 *        - Description: "Pasien webhook"
 *        - Execute as: Me
 *        - Who has access: Anyone (atau "Anyone with the link")
 *      Salin URL "Web app" yang dihasilkan, set sebagai env GSHEET_WEBHOOK_URL
 *      di server PHP.
 *   6. Buka URL aplikasi PHP, submit satu data tes, dan periksa Sheet.
 */

// Ganti dengan string panjang acak untuk autentikasi sederhana.
// Biarkan kosong untuk menerima semua request.
const SHARED_TOKEN = '';

// Nama sheet yang dipakai. Akan dibuat otomatis jika belum ada.
const SHEET_NAME = 'Pasien';

// Header kolom (urut sesuai field yang dikirim PHP).
const HEADERS = ['ID', 'Created At', 'Tanggal', 'Nama', 'No. Registrasi', 'Diagnosa'];

function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents || '{}');
    if (SHARED_TOKEN && body.token !== SHARED_TOKEN) {
      return jsonOut({ ok: false, error: 'unauthorized' }, 401);
    }
    const r = body.record || {};
    const sheet = getSheet_();
    sheet.appendRow([
      r.id || '',
      r.created_at || new Date().toISOString(),
      r.tanggal || '',
      r.nama || '',
      r.no_registrasi || '',
      r.diagnosa || '',
    ]);
    return jsonOut({ ok: true });
  } catch (err) {
    return jsonOut({ ok: false, error: String(err) }, 500);
  }
}

function doGet() {
  return jsonOut({ ok: true, info: 'Pasien webhook is alive' });
}

function getSheet_() {
  const ss = SpreadsheetApp.getActive();
  let sheet = ss.getSheetByName(SHEET_NAME);
  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
    sheet.appendRow(HEADERS);
    sheet.setFrozenRows(1);
  } else if (sheet.getLastRow() === 0) {
    sheet.appendRow(HEADERS);
    sheet.setFrozenRows(1);
  }
  return sheet;
}

function jsonOut(obj, _status) {
  // Apps Script ContentService tidak mendukung kustom status code,
  // status hanya dipakai sebagai dokumentasi internal.
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
