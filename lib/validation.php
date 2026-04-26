<?php
declare(strict_types=1);

/**
 * Validate raw form input. Returns [cleaned, errors].
 *
 * @param array<string, mixed> $input
 * @return array{0: array<string, string>, 1: array<string, string>}
 */
function validate_patient(array $input): array
{
    $cleaned = [];
    $errors  = [];

    $tgl = trim((string)($input['tanggal'] ?? ''));
    if ($tgl === '') {
        $errors['tanggal'] = 'Tanggal wajib diisi.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $tgl);
        if (!$d || $d->format('Y-m-d') !== $tgl) {
            $errors['tanggal'] = 'Format tanggal tidak valid (YYYY-MM-DD).';
        }
    }
    $cleaned['tanggal'] = $tgl;

    $nama = trim((string)($input['nama'] ?? ''));
    if ($nama === '') {
        $errors['nama'] = 'Nama wajib diisi.';
    } elseif (mb_strlen($nama) > 120) {
        $errors['nama'] = 'Nama maksimal 120 karakter.';
    }
    $cleaned['nama'] = $nama;

    $noReg = trim((string)($input['no_registrasi'] ?? ''));
    if ($noReg === '') {
        $errors['no_registrasi'] = 'No. registrasi wajib diisi.';
    } elseif (!preg_match('/^\d+$/', $noReg)) {
        $errors['no_registrasi'] = 'No. registrasi hanya boleh berisi angka.';
    } elseif (mb_strlen($noReg) > 20) {
        $errors['no_registrasi'] = 'No. registrasi maksimal 20 digit.';
    }
    $cleaned['no_registrasi'] = $noReg;

    $diagnosa = trim((string)($input['diagnosa'] ?? ''));
    if ($diagnosa === '') {
        $errors['diagnosa'] = 'Diagnosa wajib diisi.';
    } elseif (mb_strlen($diagnosa) > 1000) {
        $errors['diagnosa'] = 'Diagnosa maksimal 1000 karakter.';
    }
    $cleaned['diagnosa'] = $diagnosa;

    return [$cleaned, $errors];
}
