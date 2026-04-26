<?php
declare(strict_types=1);

require __DIR__ . '/../src/storage.php';
require __DIR__ . '/../src/validation.php';
require __DIR__ . '/../src/gsheet.php';

$config = require __DIR__ . '/../src/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$old    = [];
$errors = [];
$flash  = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(400);
        $errors['_form'] = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
        $old = $_POST;
    } else {
        [$cleaned, $errors] = validate_patient($_POST);
        if (!$errors) {
            try {
                $saved      = storage_append($config, $cleaned);
                $gsheetSent = gsheet_push($config, $saved);

                $_SESSION['flash'] = [
                    'type'    => 'success',
                    'message' => $gsheetSent
                        ? 'Data pasien berhasil disimpan & dikirim ke Google Sheets.'
                        : (($config['gsheet_webhook_url'] ?? '') !== ''
                            ? 'Data pasien tersimpan, tapi gagal sync ke Google Sheets. Cek log server.'
                            : 'Data pasien berhasil disimpan.'),
                ];
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#daftar');
                exit;
            } catch (Throwable $e) {
                error_log('[index] simpan gagal: ' . $e->getMessage());
                $errors['_form'] = 'Terjadi kesalahan saat menyimpan data. Coba lagi.';
                $old = $cleaned;
            }
        } else {
            $old = $cleaned;
        }
    }
}

$records = storage_read_all($config);
// Show newest first.
usort($records, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt_tanggal(string $iso): string
{
    $d = DateTime::createFromFormat('Y-m-d', $iso);
    if (!$d) {
        return $iso;
    }
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $d->format('j') . ' ' . $bulan[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0d9488">
<title><?= e($config['app_name']) ?> &middot; Pendaftaran Pasien</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brand: {
            50:  '#f0fdfa',
            100: '#ccfbf1',
            200: '#99f6e4',
            300: '#5eead4',
            400: '#2dd4bf',
            500: '#14b8a6',
            600: '#0d9488',
            700: '#0f766e',
            800: '#115e59',
            900: '#134e4a',
          },
        },
        fontFamily: {
          sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        },
        boxShadow: {
          glow: '0 25px 50px -12px rgba(13, 148, 136, 0.25)',
        },
      },
    },
  };
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style type="text/tailwindcss">
  @layer components {
    .field-input {
      @apply w-full rounded-xl border border-slate-200 bg-white/80 px-4 py-3 text-slate-900 placeholder-slate-400
             shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200;
    }
    .field-input.error {
      @apply border-rose-400 focus:border-rose-500 focus:ring-rose-200;
    }
  }
</style>
<style>
  body { font-family: 'Inter', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
  html { scroll-behavior: smooth; }
  .blob {
    position: absolute; filter: blur(80px); opacity: .55; pointer-events: none;
    border-radius: 9999px;
  }
  .glass {
    background: rgba(255,255,255,0.75);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
  }
  details > summary { list-style: none; cursor: pointer; }
  details > summary::-webkit-details-marker { display: none; }
  @keyframes slidein { from { transform: translateY(-12px); opacity: 0 } to { transform: translateY(0); opacity: 1 } }
  .toast { animation: slidein .25s ease-out; }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-brand-50 text-slate-800">

<!-- Decorative background blobs -->
<div aria-hidden="true" class="pointer-events-none fixed inset-0 overflow-hidden -z-10">
  <div class="blob bg-brand-300 w-[420px] h-[420px] -top-32 -left-24"></div>
  <div class="blob bg-cyan-200 w-[460px] h-[460px] top-[40%] -right-24"></div>
  <div class="blob bg-emerald-200 w-[360px] h-[360px] bottom-0 left-1/3"></div>
</div>

<!-- Header -->
<header class="sticky top-0 z-30 backdrop-blur bg-white/70 border-b border-white/60">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 py-3 flex items-center justify-between">
    <a href="#" class="flex items-center gap-2.5">
      <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-cyan-500 text-white shadow-md">
        <!-- heart pulse icon -->
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
          <path d="M3 12h4l2-5 4 10 2-5h6"/>
        </svg>
      </span>
      <span class="font-bold text-slate-900 tracking-tight"><?= e($config['app_name']) ?></span>
    </a>
    <nav class="hidden sm:flex items-center gap-6 text-sm font-medium text-slate-600">
      <a href="#form" class="hover:text-brand-700">Daftar Pasien</a>
      <a href="#daftar" class="hover:text-brand-700">Riwayat</a>
      <a href="#form" class="rounded-full bg-brand-600 px-4 py-2 text-white shadow-sm hover:bg-brand-700 transition">
        + Pasien Baru
      </a>
    </nav>
    <a href="#form" class="sm:hidden rounded-full bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm">
      + Pasien
    </a>
  </div>
</header>

<!-- Toast -->
<?php if ($flash): ?>
  <div class="mx-auto max-w-6xl px-4 sm:px-6 mt-4">
    <div class="toast flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 text-emerald-900 shadow-sm">
      <svg class="h-5 w-5 mt-0.5 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/>
      </svg>
      <div class="text-sm font-medium"><?= e($flash['message']) ?></div>
    </div>
  </div>
<?php endif; ?>

<!-- Hero -->
<section class="relative pt-10 pb-12 sm:pt-16 sm:pb-20">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 grid lg:grid-cols-2 gap-10 items-center">
    <div>
      <span class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-brand-700 shadow-sm ring-1 ring-brand-100">
        <span class="h-2 w-2 rounded-full bg-brand-500 animate-pulse"></span>
        Sistem Pendaftaran Pasien
      </span>
      <h1 class="mt-4 text-3xl sm:text-5xl font-extrabold tracking-tight text-slate-900 leading-tight">
        Catat data pasien<br class="hidden sm:block">
        <span class="bg-gradient-to-r from-brand-600 to-cyan-600 bg-clip-text text-transparent">cepat &amp; rapi.</span>
      </h1>
      <p class="mt-4 text-base sm:text-lg text-slate-600 max-w-xl">
        <?= e($config['app_tagline']) ?>. Isi formulir di samping &mdash; data tersimpan otomatis di server lokal
        dan, jika diaktifkan, juga ke Google Sheets.
      </p>
      <div class="mt-6 flex flex-wrap gap-3">
        <a href="#form" class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 transition">
          Mulai Daftar
          <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
        <a href="#daftar" class="inline-flex items-center gap-2 rounded-full bg-white/80 px-5 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-white transition">
          Lihat Riwayat
        </a>
      </div>
      <dl class="mt-8 grid grid-cols-3 gap-4 max-w-md">
        <div class="rounded-2xl bg-white/70 p-3 sm:p-4 ring-1 ring-white/60 shadow-sm">
          <dt class="text-xs text-slate-500">Total</dt>
          <dd class="text-xl sm:text-2xl font-bold text-slate-900"><?= count($records) ?></dd>
        </div>
        <div class="rounded-2xl bg-white/70 p-3 sm:p-4 ring-1 ring-white/60 shadow-sm">
          <dt class="text-xs text-slate-500">Hari ini</dt>
          <dd class="text-xl sm:text-2xl font-bold text-slate-900">
            <?= count(array_filter($records, fn($r) => ($r['tanggal'] ?? '') === date('Y-m-d'))) ?>
          </dd>
        </div>
        <div class="rounded-2xl bg-white/70 p-3 sm:p-4 ring-1 ring-white/60 shadow-sm">
          <dt class="text-xs text-slate-500">Sheets</dt>
          <dd class="text-xl sm:text-2xl font-bold <?= ($config['gsheet_webhook_url'] ?? '') !== '' ? 'text-emerald-600' : 'text-slate-400' ?>">
            <?= ($config['gsheet_webhook_url'] ?? '') !== '' ? 'On' : 'Off' ?>
          </dd>
        </div>
      </dl>
    </div>

    <!-- Form card -->
    <div id="form" class="relative">
      <div class="absolute -inset-2 bg-gradient-to-tr from-brand-200/60 via-cyan-100 to-transparent rounded-3xl blur-2xl"></div>
      <div class="relative glass rounded-3xl p-6 sm:p-8 shadow-glow ring-1 ring-white/60">
        <div class="flex items-center gap-3 mb-6">
          <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-white">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
          </span>
          <div>
            <h2 class="text-lg font-bold text-slate-900">Form Pendaftaran</h2>
            <p class="text-sm text-slate-500">Lengkapi data pasien di bawah ini.</p>
          </div>
        </div>

        <?php if (!empty($errors['_form'])): ?>
          <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
            <?= e($errors['_form']) ?>
          </div>
        <?php endif; ?>

        <form method="post" novalidate class="space-y-4">
          <input type="hidden" name="_csrf" value="<?= e($_SESSION['csrf']) ?>">

          <div>
            <label for="tanggal" class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal</label>
            <input id="tanggal" name="tanggal" type="date" required
                   value="<?= e($old['tanggal'] ?? date('Y-m-d')) ?>"
                   class="field-input <?= isset($errors['tanggal']) ? 'error' : '' ?>">
            <?php if (isset($errors['tanggal'])): ?>
              <p class="mt-1 text-xs text-rose-600"><?= e($errors['tanggal']) ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="nama" class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Pasien</label>
            <input id="nama" name="nama" type="text" required maxlength="120"
                   placeholder="cth. Andi Saputra"
                   value="<?= e($old['nama'] ?? '') ?>"
                   class="field-input <?= isset($errors['nama']) ? 'error' : '' ?>">
            <?php if (isset($errors['nama'])): ?>
              <p class="mt-1 text-xs text-rose-600"><?= e($errors['nama']) ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="no_registrasi" class="block text-sm font-semibold text-slate-700 mb-1.5">No. Registrasi</label>
            <input id="no_registrasi" name="no_registrasi" type="text" required maxlength="50"
                   placeholder="cth. REG-2026-0001"
                   value="<?= e($old['no_registrasi'] ?? '') ?>"
                   class="field-input font-mono <?= isset($errors['no_registrasi']) ? 'error' : '' ?>">
            <?php if (isset($errors['no_registrasi'])): ?>
              <p class="mt-1 text-xs text-rose-600"><?= e($errors['no_registrasi']) ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="diagnosa" class="block text-sm font-semibold text-slate-700 mb-1.5">Diagnosa</label>
            <textarea id="diagnosa" name="diagnosa" required rows="3" maxlength="1000"
                      placeholder="Tuliskan diagnosa awal pasien..."
                      class="field-input resize-none <?= isset($errors['diagnosa']) ? 'error' : '' ?>"><?= e($old['diagnosa'] ?? '') ?></textarea>
            <?php if (isset($errors['diagnosa'])): ?>
              <p class="mt-1 text-xs text-rose-600"><?= e($errors['diagnosa']) ?></p>
            <?php endif; ?>
          </div>

          <button type="submit"
                  class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-600 to-cyan-600 px-5 py-3 text-sm font-semibold text-white shadow-md hover:shadow-lg active:scale-[0.99] transition">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>
            </svg>
            Simpan Data Pasien
          </button>
          <p class="text-xs text-center text-slate-500">
            Data tersimpan di <code class="text-slate-700">data/patients.json</code> &amp; <code class="text-slate-700">.csv</code>.
          </p>
        </form>
      </div>
    </div>
  </div>
</section>

<!-- Daftar pasien -->
<section id="daftar" class="pb-16 sm:pb-24">
  <div class="mx-auto max-w-6xl px-4 sm:px-6">
    <div class="flex items-end justify-between mb-5 gap-3">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Riwayat Pendaftaran</h2>
        <p class="text-sm text-slate-500">Daftar pasien yang sudah tersimpan, paling baru di atas.</p>
      </div>
      <span class="rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
        <?= count($records) ?> entri
      </span>
    </div>

    <?php if (empty($records)): ?>
      <div class="glass rounded-3xl p-10 text-center ring-1 ring-white/60 shadow-sm">
        <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600">
          <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/>
          </svg>
        </div>
        <p class="font-semibold text-slate-800">Belum ada data pasien</p>
        <p class="text-sm text-slate-500 mt-1">Mulai dengan mengisi form di atas.</p>
      </div>
    <?php else: ?>
      <!-- Mobile cards -->
      <div class="space-y-3 md:hidden">
        <?php foreach ($records as $r): ?>
          <article class="glass rounded-2xl p-4 ring-1 ring-white/60 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-wide text-brand-700 font-semibold"><?= e(fmt_tanggal((string)$r['tanggal'])) ?></div>
                <div class="mt-0.5 text-base font-bold text-slate-900"><?= e($r['nama']) ?></div>
              </div>
              <span class="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 ring-1 ring-brand-100 font-mono"><?= e($r['no_registrasi']) ?></span>
            </div>
            <details class="mt-2 group">
              <summary class="text-sm text-slate-600 line-clamp-2 group-open:line-clamp-none"><?= e($r['diagnosa']) ?></summary>
            </details>
          </article>
        <?php endforeach; ?>
      </div>

      <!-- Desktop table -->
      <div class="hidden md:block glass rounded-2xl ring-1 ring-white/60 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200/70 text-sm">
            <thead class="bg-white/60">
              <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <th class="px-4 py-3">Tanggal</th>
                <th class="px-4 py-3">Nama</th>
                <th class="px-4 py-3">No. Registrasi</th>
                <th class="px-4 py-3">Diagnosa</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/60">
              <?php foreach ($records as $r): ?>
                <tr class="hover:bg-brand-50/40 transition">
                  <td class="px-4 py-3 align-top text-slate-700 whitespace-nowrap"><?= e(fmt_tanggal((string)$r['tanggal'])) ?></td>
                  <td class="px-4 py-3 align-top font-semibold text-slate-900"><?= e($r['nama']) ?></td>
                  <td class="px-4 py-3 align-top">
                    <span class="rounded-md bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-700 ring-1 ring-brand-100 font-mono"><?= e($r['no_registrasi']) ?></span>
                  </td>
                  <td class="px-4 py-3 align-top text-slate-600 max-w-md"><?= nl2br(e($r['diagnosa'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<footer class="border-t border-white/60 bg-white/60 backdrop-blur">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 py-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-sm text-slate-500">
    <p>&copy; <?= date('Y') ?> <?= e($config['app_name']) ?>. Semua hak dilindungi.</p>
    <p>Dibangun dengan PHP &middot; data lokal di <code>data/</code></p>
  </div>
</footer>

</body>
</html>
