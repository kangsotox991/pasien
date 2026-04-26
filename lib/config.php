<?php
declare(strict_types=1);

/**
 * Application configuration.
 *
 * Sensitive / per-deployment values are read from environment variables so
 * the file itself can be committed safely.
 */

return [
    // Brand / display
    'app_name'    => getenv('APP_NAME')    ?: 'Klinik Sehat Sentosa',
    'app_tagline' => getenv('APP_TAGLINE') ?: 'Layanan kesehatan terpercaya untuk keluarga Anda',

    // Storage paths (relative to project root, sibling of lib/)
    'data_dir'  => __DIR__ . '/../data',
    'json_file' => __DIR__ . '/../data/patients.json',
    'csv_file'  => __DIR__ . '/../data/patients.csv',

    // Google Sheets webhook (Apps Script Web App URL).
    // Leave empty to disable Google Sheets sync.
    'gsheet_webhook_url' => getenv('GSHEET_WEBHOOK_URL') ?: '',

    // Optional shared secret sent in the webhook payload as `token`.
    // Set the same value inside the Apps Script for basic protection.
    'gsheet_webhook_token' => getenv('GSHEET_WEBHOOK_TOKEN') ?: '',
];
