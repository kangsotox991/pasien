<?php
declare(strict_types=1);

/**
 * Push a patient record to a Google Apps Script Web App.
 *
 * Returns true on success, false otherwise. Errors are logged but do not
 * raise — Google Sheets sync is a best-effort secondary store.
 */
function gsheet_push(array $config, array $record): bool
{
    $url = $config['gsheet_webhook_url'] ?? '';
    if ($url === '') {
        return false;
    }

    $payload = json_encode([
        'token'  => $config['gsheet_webhook_token'] ?? '',
        'record' => $record,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status >= 400) {
        error_log(sprintf(
            '[gsheet] sync gagal status=%d err=%s body=%s',
            $status,
            $err,
            is_string($response) ? substr($response, 0, 200) : ''
        ));
        return false;
    }
    return true;
}
