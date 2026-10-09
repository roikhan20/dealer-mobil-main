<?php
/** Layout header. Variabel: $pageTitle (string), $area ('public'|'staff'). */
$pageTitle = $pageTitle ?? 'Beranda';
$area = $area ?? 'public';
$user = auth_user();
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');

/** Menu staf per grup: [file, label, ikon, peran yang boleh]. Grup dengan >1 item tampil sebagai dropdown. */
$staffGroups = [
    'Dashboard'  => [['index.php', 'Dashboard', 'speedometer', ['admin_inventaris', 'sales', 'service_advisor', 'manager']]],
    'Inventaris' => [['vehicles.php', 'Master kendaraan', 'car-front', ['admin_inventaris']],
                     ['units.php', 'Stok unit (VIN)', 'upc-scan', ['admin_inventaris', 'manager']],
                     ['leasing.php', 'Parameter leasing', 'bank', ['admin_inventaris', 'manager']]],
    'Penjualan'  => [['leads.php', 'Leads / CRM', 'people', ['sales', 'manager']],
                     ['testdrives.php', 'Jadwal test drive', 'calendar-check', ['sales', 'manager']],
                     ['spk_list.php', 'Daftar SPK', 'file-earmark-text', ['sales', 'manager']],
                     ['spk_form.php', 'Buat SPK baru', 'file-earmark-plus', ['sales']]],
    'Bengkel'    => [['services.php', 'Slot servis', 'wrench-adjustable', ['service_advisor', 'manager']],
                     ['service_history.php', 'Riwayat servis', 'clock-history', ['service_advisor', 'manager']],
                     ['service_record_form.php', 'Catat servis walk-in', 'clipboard-plus', ['service_advisor']]],
    'Pengguna'   => [['users.php', 'Pengguna', 'person-gear', ['manager']]],
];
$publicMenu = [
    ['index.php',           'Katalog',            'grid'],
    ['testdrive.php',       'Test Drive',         'speedometer2'],
    ['quotation.php',       'Minta Penawaran',    'chat-dots'],
    ['service_booking.php', 'Booking Servis',     'wrench-adjustable'],
    ['booking_status.php',  'Cek Booking',        'ticket-detailed'],
    ['vin_lookup.php',      'Riwayat Servis',     'search'],
];
$inAdmin = str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
?>
<!doctype html>
<html lang="id" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | <?= e(cfg('app', 'company')) ?></title>
<script>try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('theme') || 'dark'); } catch (_) {}</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
<script>
// Foto gagal dimuat (mis. offline) -> ganti dengan placeholder ikon mobil.
document.addEventListener('error', (e) => {
  const img = e.target;
  if (!(img instanceof HTMLImageElement) || !img.classList.contains('car-img') || img.dataset.failed) return;
  img.dataset.failed = '1';
  const ph = document.createElement('div');
  ph.className = 'car-img-ph' + (img.classList.contains('car-thumb') ? ' car-thumb' : '');
  ph.innerHTML = '<i class="bi bi-car-front-fill"></i>';
  img.replaceWith(ph);
}, true);
</script>
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top navbar-lux">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= e(url($area === 'staff' ? 'admin/index.php' : 'index.php')) ?>"><i class="bi bi-rocket-takeoff-fill text-gold me-2"></i>PENERBANG <span class="text-gold">ROKET</span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
      <?php if ($area === 'staff' && $user): ?>
        <?php foreach ($staffGroups as $_g => $_items):
          $_items = array_values(array_filter($_items, fn($x) => in_array($user['role'], $x[3], true)));
          if (!$_items) continue;
          $_act = $inAdmin && in_array($current, array_column($_items, 0), true);
          if (count($_items) === 1): [$_mf, $_ml, $_mi] = $_items[0]; ?>
          <li class="nav-item"><a class="nav-link <?= $_act ? 'active' : '' ?>" href="<?= e(url('admin/' . $_mf)) ?>"><i class="bi bi-<?= $_mi ?> me-1"></i><?= e($_ml) ?></a></li>
          <?php else: ?>
          <li class="nav-item dropdown"><a class="nav-link dropdown-toggle <?= $_act ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><?= e($_g) ?></a>
            <ul class="dropdown-menu">
              <?php foreach ($_items as [$_mf, $_ml, $_mi]): ?><li><a class="dropdown-item <?= $inAdmin && $current === $_mf ? 'active' : '' ?>" href="<?= e(url('admin/' . $_mf)) ?>"><i class="bi bi-<?= $_mi ?> me-2 text-gold"></i><?= e($_ml) ?></a></li><?php endforeach; ?>
            </ul></li>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($publicMenu as [$_mf, $_ml, $_mi]): ?>
          <li class="nav-item"><a class="nav-link <?= !$inAdmin && $current === $_mf ? 'active' : '' ?>" href="<?= e(url($_mf)) ?>"><i class="bi bi-<?= $_mi ?> me-1 d-lg-none"></i><?= e($_ml) ?></a></li>
        <?php endforeach; ?>
      <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center gap-2 flex-wrap py-2 py-lg-0">
        <button class="btn btn-sm btn-outline-secondary" id="themeToggle" type="button" title="Ganti tema"><i class="bi bi-circle-half"></i></button>
        <?php if ($user): ?>
          <?php if ($area !== 'staff'): ?><a class="btn btn-sm btn-gold" href="<?= e(url('admin/index.php')) ?>"><i class="bi bi-speedometer me-1"></i>Dashboard</a>
          <?php else: ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('index.php')) ?>" title="Lihat situs pelanggan"><i class="bi bi-shop"></i></a><?php endif; ?>
          <a class="btn btn-sm btn-outline-secondary text-truncate" style="max-width:240px" href="<?= e(url('admin/profile.php')) ?>" title="Profil &amp; ganti password"><i class="bi bi-person-circle me-1"></i><?= e($user['name']) ?> <span class="d-none d-xxl-inline text-secondary">· <?= e(ROLE_LABELS[$user['role']]) ?></span></a>
          <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0"><?= csrf_field() ?><button class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Keluar</button></form>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-light" href="<?= e(url('login.php')) ?>"><i class="bi bi-person-lock me-1"></i>Login Staf</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<main class="container py-4">
<?php foreach (flash_pull() as [$t, $m]): ?>
  <div class="alert alert-<?= e($t) ?> alert-dismissible fade show" role="alert"><?= e($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; ?>
