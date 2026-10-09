<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role();
$area = 'staff'; $pdo = db(); $role = $user['role'];
$one = fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();

$pageTitle = 'Dashboard';
include BASE_PATH . '/app/views/header.php';
?>
<h3 class="mb-1">Selamat datang, <?= e($user['name']) ?></h3>
<p class="text-secondary"><?= e(ROLE_LABELS[$role]) ?> · <?= e(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][(int)date('w')] . ', ' . date('d-m-Y')) ?></p>
<?php
$tiles = [
  'admin_inventaris' => [['admin/vehicle_form.php', 'plus-square', 'Tambah varian', 'Mobil baru ke katalog'], ['admin/vehicles.php', 'car-front', 'Master kendaraan', 'Edit harga, foto, warna'],
                         ['admin/units.php', 'upc-scan', 'Stok unit VIN', 'Tambah / cek unit fisik'], ['admin/leasing.php', 'bank', 'Parameter leasing', 'Bunga & asuransi']],
  'sales'            => [['admin/leads.php', 'people', 'Leads saya', 'Follow-up prospek'], ['admin/testdrives.php', 'calendar-check', 'Test drive', 'Konfirmasi jadwal'],
                         ['admin/spk_form.php', 'file-earmark-plus', 'SPK baru', 'Kunci unit untuk pembeli'], ['admin/spk_list.php', 'file-earmark-text', 'Daftar SPK', 'Cetak & serah terima']],
  'service_advisor'  => [['admin/services.php', 'wrench-adjustable', 'Slot servis', 'Booking hari ini'], ['admin/service_record_form.php', 'clipboard-plus', 'Catat walk-in', 'Servis tanpa booking'],
                         ['admin/service_history.php', 'clock-history', 'Riwayat servis', 'Semua pekerjaan bengkel'], ['vin_lookup.php', 'search', 'Cek VIN', 'Status garansi']],
  'manager'          => [['admin/leads.php', 'people', 'Semua leads', 'Pantau & alihkan'], ['admin/testdrives.php', 'calendar-check', 'Test drive', 'Jadwal seluruh sales'],
                         ['admin/spk_list.php', 'file-earmark-text', 'Daftar SPK', 'Seluruh transaksi'], ['admin/units.php', 'upc-scan', 'Stok VIN', 'Ready / booked / delivered'],
                         ['admin/services.php', 'wrench-adjustable', 'Bengkel', 'Slot & kuota'], ['admin/users.php', 'person-gear', 'Pengguna', 'Akun staf']],
];
?>
<div class="row g-3 mb-2">
  <?php foreach ($tiles[$role] as [$href, $icon, $title, $sub]): ?>
    <div class="col-6 col-md-4 col-xl-<?= count($tiles[$role]) > 4 ? 2 : 3 ?>"><a class="card quick-tile" href="<?= e(url($href)) ?>"><i class="bi bi-<?= $icon ?>"></i><div><div class="fw-semibold"><?= e($title) ?></div><div class="small text-secondary"><?= e($sub) ?></div></div></a></div>
  <?php endforeach; ?>
</div>

<?php if ($role === 'admin_inventaris' || $role === 'manager'):
  $stock = $pdo->query("SELECT status, COUNT(*) c FROM vehicle_units GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR); ?>
  <h5 class="mt-4">Inventaris</h5>
  <div class="row g-3 mb-3">
    <?php foreach (['ready' => 'Ready', 'booked' => 'Booked', 'delivered' => 'Delivered'] as $k => $l): ?>
      <div class="col-6 col-md-3"><div class="card stat-card p-3"><div class="text-secondary small"><?= $l ?></div><div class="display-6"><?= (int)($stock[$k] ?? 0) ?></div></div></div>
    <?php endforeach; ?>
    <div class="col-6 col-md-3"><div class="card stat-card p-3"><div class="text-secondary small">Varian aktif</div><div class="display-6"><?= (int)$one('SELECT COUNT(*) FROM variants WHERE is_active=1') ?></div></div></div>
  </div>
  <?php if ($role === 'admin_inventaris'):
    $empty = $pdo->query("SELECT v.id, CONCAT(b.name,' ',v.name) AS label FROM variants v JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id
                           WHERE v.is_active = 1 AND NOT EXISTS (SELECT 1 FROM vehicle_units u WHERE u.variant_id = v.id AND u.status = 'ready') ORDER BY label")->fetchAll(); ?>
  <div class="card p-3 mb-3"><h6>Varian tanpa stok Ready <span class="badge text-bg-secondary"><?= count($empty) ?></span> <small class="text-secondary fw-normal">— tampil "Indent" di katalog</small></h6>
    <?php if ($empty): ?><div class="d-flex flex-wrap gap-2"><?php foreach ($empty as $x): ?><a class="badge text-bg-dark border text-decoration-none" href="<?= e(url('admin/units.php?variant=' . (int)$x['id'])) ?>"><?= e($x['label']) ?></a><?php endforeach; ?></div>
    <?php else: ?><div class="small text-secondary">Semua varian punya stok.</div><?php endif; ?></div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($role === 'sales'):
  $mine = $pdo->prepare('SELECT status, COUNT(*) c FROM leads WHERE sales_id = ? GROUP BY status'); $mine->execute([$user['id']]); $mine = $mine->fetchAll(PDO::FETCH_KEY_PAIR);
  $mySpk = (int)$one("SELECT COUNT(*) FROM spk WHERE sales_id = ? AND status <> 'cancelled'", [$user['id']]); ?>
  <h5 class="mt-4">Pipeline Saya</h5>
  <div class="row g-3 mb-3">
    <?php foreach (['cold' => 'Cold', 'warm' => 'Warm', 'hot' => 'Hot', 'won' => 'Won'] as $k => $l): ?>
      <div class="col-6 col-md-3"><a href="<?= e(url('admin/leads.php?status=' . $k)) ?>" class="text-decoration-none text-reset"><div class="card stat-card p-3"><div class="text-secondary small"><?= $l ?></div><div class="display-6"><?= (int)($mine[$k] ?? 0) ?></div></div></a></div>
    <?php endforeach; ?>
  </div>
  <div class="card p-3 mb-3">SPK aktif/terkirim: <strong class="text-gold"><?= $mySpk ?></strong></div>
  <?php
    $tds = $pdo->prepare("SELECT t.*, l.name AS customer, CONCAT(b.name,' ',v.name) AS car FROM test_drives t JOIN leads l ON l.id=t.lead_id
                           JOIN variants v ON v.id=t.variant_id JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id
                          WHERE l.sales_id = ? AND t.status IN ('requested','confirmed') AND t.scheduled_date >= CURDATE() ORDER BY t.scheduled_date, t.scheduled_time LIMIT 8");
    $tds->execute([$user['id']]); $tds = $tds->fetchAll();
    $hot = $pdo->prepare("SELECT l.id, l.name, l.phone, l.status, CONCAT(b.name,' ',v.name) AS car FROM leads l LEFT JOIN variants v ON v.id=l.variant_id
                           LEFT JOIN models m ON m.id=v.model_id LEFT JOIN brands b ON b.id=m.brand_id
                          WHERE l.sales_id = ? AND l.status IN ('hot','warm','cold') ORDER BY FIELD(l.status,'hot','warm','cold'), l.created_at DESC LIMIT 8");
    $hot->execute([$user['id']]); $hot = $hot->fetchAll();
  ?>
  <div class="row g-3 mb-3">
    <div class="col-lg-6"><div class="card p-3 h-100"><h6>Test drive mendatang</h6>
      <?php foreach ($tds as $t): ?><div class="d-flex justify-content-between border-top py-2 small"><div><strong><?= e(date('d M', strtotime($t['scheduled_date']))) ?> <?= e(substr($t['scheduled_time'], 0, 5)) ?></strong> · <?= e($t['customer']) ?><div class="text-secondary"><?= e($t['car']) ?></div></div><div><?= badge($t['status']) ?></div></div><?php endforeach; ?>
      <?php if (!$tds): ?><div class="small text-secondary">Tidak ada jadwal.</div><?php endif; ?>
      <a class="small mt-2" href="<?= e(url('admin/testdrives.php')) ?>">Kelola jadwal &rarr;</a></div></div>
    <div class="col-lg-6"><div class="card p-3 h-100"><h6>Prospek perlu follow-up</h6>
      <?php foreach ($hot as $h): ?><a class="d-flex justify-content-between border-top py-2 small text-reset text-decoration-none" href="<?= e(url('admin/lead_view.php?id=' . (int)$h['id'])) ?>"><div><strong><?= e($h['name']) ?></strong> · <?= e($h['phone']) ?><div class="text-secondary"><?= e($h['car'] ?? '-') ?></div></div><div><?= badge($h['status']) ?></div></a><?php endforeach; ?>
      <?php if (!$hot): ?><div class="small text-secondary">Belum ada lead aktif. Lead dari form Test Drive & Penawaran otomatis dibagikan ke sales.</div><?php endif; ?></div></div>
  </div>
<?php endif; ?>

<?php if ($role === 'manager'):
  $perf = $pdo->query(
    "SELECT u.id, u.name,
        (SELECT COUNT(*) FROM leads l WHERE l.sales_id = u.id) AS leads,
        (SELECT COUNT(*) FROM leads l WHERE l.sales_id = u.id AND l.status = 'hot') AS hot,
        (SELECT COUNT(DISTINCT s.lead_id) FROM spk s WHERE s.sales_id = u.id AND s.lead_id IS NOT NULL AND s.status <> 'cancelled') AS converted,
        (SELECT COUNT(*) FROM spk s WHERE s.sales_id = u.id AND s.status <> 'cancelled') AS spk,
        (SELECT COALESCE(SUM(s.otr_price),0) FROM spk s WHERE s.sales_id = u.id AND s.status <> 'cancelled') AS revenue
       FROM users u WHERE u.role = 'sales' AND u.is_active = 1 ORDER BY spk DESC, leads DESC"
  )->fetchAll();
  $tot = ['leads' => array_sum(array_column($perf, 'leads')), 'spk' => array_sum(array_column($perf, 'spk')), 'rev' => array_sum(array_column($perf, 'revenue'))];
  $month = $pdo->query("SELECT COUNT(*) FROM spk WHERE status <> 'cancelled' AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn(); ?>
  <h5 class="mt-4">Laporan Eksekutif</h5>
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card stat-card p-3"><div class="text-secondary small">Total Leads</div><div class="display-6"><?= $tot['leads'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card p-3"><div class="text-secondary small">Total SPK</div><div class="display-6"><?= $tot['spk'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card p-3"><div class="text-secondary small">SPK bulan ini</div><div class="display-6"><?= (int)$month ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card p-3"><div class="text-secondary small">Nilai SPK (OTR)</div><div class="fs-5 fw-bold text-gold"><?= rupiah($tot['rev']) ?></div></div></div>
  </div>
  <div class="card p-3"><h6>Performa Sales Executive</h6>
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Sales</th><th class="text-end">Leads</th><th class="text-end">Hot</th><th class="text-end">SPK</th><th style="width:220px">Konversi Lead → SPK</th><th class="text-end">Nilai OTR</th></tr></thead><tbody>
      <?php foreach ($perf as $r): $conv = $r['leads'] > 0 ? round($r['converted'] / $r['leads'] * 100, 1) : 0; ?>
        <tr><td><?= e($r['name']) ?></td><td class="text-end"><?= (int)$r['leads'] ?></td><td class="text-end"><?= (int)$r['hot'] ?></td><td class="text-end"><?= (int)$r['spk'] ?></td>
          <td><div class="progress" role="progressbar"><div class="progress-bar bg-warning text-dark" style="width:<?= $conv ?>%"><?= $conv ?>%</div></div></td>
          <td class="text-end"><?= rupiah($r['revenue']) ?></td></tr>
      <?php endforeach; if (!$perf): ?><tr><td colspan="6" class="text-secondary">Belum ada data.</td></tr><?php endif; ?>
      </tbody></table></div></div>
<?php endif; ?>

<?php if (in_array($role, ['service_advisor', 'manager'], true)):
  $today = date('Y-m-d'); ?>
  <h5 class="mt-4">Bengkel Hari Ini</h5>
  <div class="row g-3">
    <div class="col-6 col-md-3"><div class="card stat-card p-3"><div class="text-secondary small">Booking hari ini</div><div class="display-6"><?= (int)$one("SELECT COUNT(*) FROM service_bookings WHERE service_date = ? AND status <> 'cancelled'", [$today]) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card p-3"><div class="text-secondary small">Sisa slot</div><div class="display-6"><?= ServiceBookingService::remaining($pdo, $today) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card p-3"><div class="text-secondary small">Sedang dikerjakan</div><div class="display-6"><?= (int)$one("SELECT COUNT(*) FROM service_bookings WHERE status = 'in_progress'") ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card p-3"><div class="text-secondary small">Booking 7 hari ke depan</div><div class="display-6"><?= (int)$one("SELECT COUNT(*) FROM service_bookings WHERE status = 'booked' AND service_date > CURDATE() AND service_date <= CURDATE() + INTERVAL 7 DAY") ?></div></div></div>
  </div>
  <a class="btn btn-outline-gold btn-sm mt-3" href="<?= e(url('admin/services.php')) ?>">Buka jadwal bengkel hari ini &rarr;</a>
<?php endif; ?>
<?php include BASE_PATH . '/app/views/footer.php';
