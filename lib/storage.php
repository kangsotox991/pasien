<?php
declare(strict_types=1);

/**
 * File-based storage for patient records.
 *
 * Records are persisted as a JSON array in `data/patients.json` and mirrored
 * to `data/patients.csv` for easy spreadsheet import.
 */

const PATIENT_CSV_HEADER = [
    'id', 'created_at', 'tanggal', 'nama', 'no_registrasi', 'diagnosa',
];

function storage_ensure_files(array $config): void
{
    if (!is_dir($config['data_dir'])) {
        mkdir($config['data_dir'], 0775, true);
    }
    if (!file_exists($config['json_file'])) {
        file_put_contents($config['json_file'], "[]\n");
    }
    if (!file_exists($config['csv_file'])) {
        $fp = fopen($config['csv_file'], 'w');
        fputcsv($fp, PATIENT_CSV_HEADER);
        fclose($fp);
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function storage_read_all(array $config): array
{
    storage_ensure_files($config);
    $raw = file_get_contents($config['json_file']);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function storage_append(array $config, array $record): array
{
    storage_ensure_files($config);

    $fp = fopen($config['json_file'], 'c+');
    if ($fp === false) {
        throw new RuntimeException('Tidak dapat membuka file penyimpanan.');
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        throw new RuntimeException('Tidak dapat mengunci file penyimpanan.');
    }

    try {
        $contents = stream_get_contents($fp) ?: '[]';
        $records  = json_decode($contents, true);
        if (!is_array($records)) {
            $records = [];
        }

        $record['id']         = bin2hex(random_bytes(6));
        $record['created_at'] = date('c');

        $records[] = $record;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        fflush($fp);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    // Append to CSV mirror.
    $csv = fopen($config['csv_file'], 'a');
    if ($csv !== false) {
        fputcsv($csv, [
            $record['id'],
            $record['created_at'],
            $record['tanggal'],
            $record['nama'],
            $record['no_registrasi'],
            $record['diagnosa'],
        ]);
        fclose($csv);
    }

    return $record;
}

/**
 * Rewrite the CSV mirror from the current JSON state.
 */
function storage_rewrite_csv(array $config, array $records): void
{
    $fp = fopen($config['csv_file'], 'w');
    if ($fp === false) {
        return;
    }
    fputcsv($fp, PATIENT_CSV_HEADER);
    foreach ($records as $r) {
        fputcsv($fp, [
            $r['id']            ?? '',
            $r['created_at']    ?? '',
            $r['tanggal']       ?? '',
            $r['nama']          ?? '',
            $r['no_registrasi'] ?? '',
            $r['diagnosa']      ?? '',
        ]);
    }
    fclose($fp);
}

/**
 * Update an existing record by id. Returns the updated record, or null if not found.
 */
function storage_update(array $config, string $id, array $fields): ?array
{
    storage_ensure_files($config);

    $fp = fopen($config['json_file'], 'c+');
    if ($fp === false) {
        throw new RuntimeException('Tidak dapat membuka file penyimpanan.');
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        throw new RuntimeException('Tidak dapat mengunci file penyimpanan.');
    }

    $updated = null;
    try {
        $contents = stream_get_contents($fp) ?: '[]';
        $records  = json_decode($contents, true);
        if (!is_array($records)) {
            $records = [];
        }
        foreach ($records as &$r) {
            if (($r['id'] ?? null) === $id) {
                $r['tanggal']       = $fields['tanggal']       ?? $r['tanggal']       ?? '';
                $r['nama']          = $fields['nama']          ?? $r['nama']          ?? '';
                $r['no_registrasi'] = $fields['no_registrasi'] ?? $r['no_registrasi'] ?? '';
                $r['diagnosa']      = $fields['diagnosa']      ?? $r['diagnosa']      ?? '';
                $r['updated_at']    = date('c');
                $updated = $r;
                break;
            }
        }
        unset($r);
        if ($updated !== null) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
            fflush($fp);
        }
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    if ($updated !== null) {
        storage_rewrite_csv($config, $records);
    }
    return $updated;
}

/**
 * Delete a record by id. Returns true if deleted, false if not found.
 */
function storage_delete(array $config, string $id): bool
{
    storage_ensure_files($config);

    $fp = fopen($config['json_file'], 'c+');
    if ($fp === false) {
        throw new RuntimeException('Tidak dapat membuka file penyimpanan.');
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        throw new RuntimeException('Tidak dapat mengunci file penyimpanan.');
    }

    $deleted = false;
    $records = [];
    try {
        $contents = stream_get_contents($fp) ?: '[]';
        $records  = json_decode($contents, true);
        if (!is_array($records)) {
            $records = [];
        }
        $kept = [];
        foreach ($records as $r) {
            if (($r['id'] ?? null) === $id) {
                $deleted = true;
                continue;
            }
            $kept[] = $r;
        }
        if ($deleted) {
            $records = $kept;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
            fflush($fp);
        }
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    if ($deleted) {
        storage_rewrite_csv($config, $records);
    }
    return $deleted;
}
