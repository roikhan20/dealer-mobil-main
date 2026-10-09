<?php
/** Verifikasi keaslian dokumen (tujuan QR Code). Hanya menampilkan data minimum. */
require __DIR__ . '/../app/bootstrap.php';
$t = input('t');
$spk = null;
if (preg_match('/^[a-f0-9]{32}$/', $t)) {
    $st = db()->prepare('SELECT s.spk_no, s.customer_name, s.status, s.created_at, s.booking_fee, u.vin, v.name AS variant
                           FROM spk s JOIN vehicle_units u ON u.id = s.unit_id JOIN variants v ON v.id = u.variant_id WHERE s.verify_token = ?');
    $st->execute([$t]);
    $spk = $st->fetch();
}
$mask = fn(string $n) => mb_substr($n, 0, 1) . str_repeat('*', max(2, mb_strlen($n) - 2)) . mb_substr($n, -1);
$pageTitle = 'Verifikasi Dokumen';
include BASE_PATH . '/app/views/header.php';
?>
<div class="row justify-content-center"><div class="col-md-6"><div class="card p-4 text-center">
<?php if ($spk): ?>
  <i class="bi bi-patch-check-fill text-success display-4"></i>
  <h4 class="mt-2">Dokumen Asli</h4>
  <table class="table table-sm text-start mt-3 mb-0">
    <tr><td>No. SPK</td><td><?= e($spk['spk_no']) ?></td></tr>
    <tr><td>Pemesan</td><td><?= e($mask($spk['customer_name'])) ?></td></tr>
    <tr><td>Kendaraan</td><td><?= e($spk['variant']) ?></td></tr>
    <tr><td>VIN</td><td><?= e(substr($spk['vin'], 0, 8)) ?>********* </td></tr>
    <tr><td>Tanggal terbit</td><td><?= e(date('d M Y', strtotime($spk['created_at']))) ?></td></tr>
    <tr><td>Status</td><td><?= badge($spk['status']) ?></td></tr>
  </table>
<?php else: ?>
  <i class="bi bi-x-octagon-fill text-danger display-4"></i><h4 class="mt-2">Dokumen tidak ditemukan</h4><p class="text-secondary mb-0">Kode verifikasi tidak valid.</p>
<?php endif; ?>
</div></div></div>
<?php include BASE_PATH . '/app/views/footer.php';
