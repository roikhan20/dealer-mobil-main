<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('sales', 'manager'); $area = 'staff'; $pdo = db();
$id = input_int('id');
$st = $pdo->prepare("SELECT s.*, u.vin, u.engine_no, CONCAT(b.name,' ',v.name) AS car, c.name AS color, sl.name AS sales_name, lp.name AS partner
    FROM spk s JOIN vehicle_units u ON u.id=s.unit_id JOIN variants v ON v.id=u.variant_id JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id
    LEFT JOIN variant_colors c ON c.id=u.color_id JOIN users sl ON sl.id=s.sales_id LEFT JOIN leasing_partners lp ON lp.id=s.leasing_partner_id WHERE s.id = ?");
$st->execute([$id]); $s = $st->fetch();
if (!$s) { http_response_code(404); exit('SPK tidak ditemukan.'); }
if ($user['role'] === 'sales' && (int)$s['sales_id'] !== (int)$user['id']) { http_response_code(403); exit('403 - SPK ini bukan milik Anda.'); }

if (is_post()) {
    try {
        if (input('action') === 'cancel')  { SpkService::cancel($pdo, $id);  flash('success', 'SPK dibatalkan, unit kembali Ready.'); }
        if (input('action') === 'deliver') { SpkService::deliver($pdo, $id); flash('success', 'Unit diserahterimakan. Masa garansi dimulai.'); }
    } catch (DomainException $ex) { flash('danger', $ex->getMessage()); }
    redirect('admin/spk_view.php?id=' . $id);
}
$pageTitle = $s['spk_no'];
include BASE_PATH . '/app/views/header.php';
?>
<a href="<?= e(url('admin/spk_list.php')) ?>" class="small text-decoration-none text-secondary">&larr; Daftar SPK</a>
<div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-2">
  <h4 class="mb-0"><?= e($s['spk_no']) ?> <?= badge($s['status']) ?></h4>
  <div class="d-flex gap-2">
    <a class="btn btn-gold btn-sm" target="_blank" href="<?= e(url('admin/spk_print.php?id=' . $id . '&doc=spk')) ?>"><i class="bi bi-printer"></i> Cetak SPK</a>
    <a class="btn btn-outline-gold btn-sm" target="_blank" href="<?= e(url('admin/spk_print.php?id=' . $id . '&doc=invoice')) ?>"><i class="bi bi-receipt"></i> Invoice Booking Fee</a>
  </div>
</div>
<div class="row g-3">
  <div class="col-md-6"><div class="card p-3 h-100"><h6 class="text-gold">Kendaraan</h6><table class="table table-sm mb-0">
    <tr><td>Unit</td><td><?= e($s['car']) ?></td></tr><tr><td>Warna</td><td><?= e($s['color'] ?? '-') ?></td></tr>
    <tr><td>VIN</td><td class="font-monospace"><?= e($s['vin']) ?></td></tr><tr><td>No. Mesin</td><td><?= e($s['engine_no']) ?></td></tr>
    <tr><td>Harga OTR</td><td><?= rupiah($s['otr_price']) ?></td></tr><tr><td>Sales</td><td><?= e($s['sales_name']) ?></td></tr></table></div></div>
  <div class="col-md-6"><div class="card p-3 h-100"><h6 class="text-gold">Pembeli</h6><table class="table table-sm mb-0">
    <tr><td>Nama</td><td><?= e($s['customer_name']) ?></td></tr><tr><td>NIK</td><td><?= e($s['nik']) ?></td></tr>
    <tr><td>HP</td><td><?= e($s['phone']) ?></td></tr><tr><td>Alamat</td><td><?= e($s['address']) ?></td></tr></table></div></div>
  <div class="col-12"><div class="card p-3"><h6 class="text-gold">Pembayaran — <?= e(strtoupper($s['payment_method'])) ?></h6>
    <table class="table table-sm mb-0">
    <?php if ($s['payment_method'] === 'kredit'): ?>
      <tr><td>Leasing</td><td><?= e($s['partner'] ?? '-') ?> · <?= (int)$s['tenor'] ?> bln · bunga <?= e($s['interest_rate']) ?>% flat</td></tr>
      <tr><td>DP Murni</td><td><?= rupiah($s['dp_amount']) ?></td></tr><tr><td>Biaya Admin</td><td><?= rupiah($s['admin_fee']) ?></td></tr>
      <tr><td>Asuransi <?= e($s['insurance_type']) ?></td><td><?= rupiah($s['insurance_amount']) ?></td></tr>
      <tr><td>Angsuran / bulan</td><td><?= rupiah($s['installment']) ?> (<?= $s['addm'] ? 'ADDM' : 'ADDB' ?>)</td></tr>
      <tr class="fw-bold"><td>TDP</td><td class="text-gold"><?= rupiah($s['tdp']) ?></td></tr>
    <?php endif; ?>
      <tr><td>Booking Fee</td><td><?= rupiah($s['booking_fee']) ?></td></tr></table></div></div>
</div>
<?php if ($s['status'] === 'active'): ?>
<div class="d-flex gap-2 mt-3">
  <form method="post" onsubmit="return confirm('Serahterimakan unit ke pelanggan?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="deliver"><button class="btn btn-success">Serah Terima Unit</button></form>
  <form method="post" onsubmit="return confirm('Batalkan SPK? Unit akan kembali Ready.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="cancel"><button class="btn btn-outline-danger">Batalkan SPK</button></form>
</div><?php endif; ?>
<?php include BASE_PATH . '/app/views/footer.php';
