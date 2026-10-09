<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('sales'); $area = 'staff'; $pdo = db();
$leadId = input_int('lead_id');
$lead = null;
if ($leadId) {
    $st = $pdo->prepare('SELECT * FROM leads WHERE id = ? AND sales_id = ?');          // Policy: hanya lead milik sendiri
    $st->execute([$leadId, $user['id']]); $lead = $st->fetch();
    if (!$lead) { http_response_code(403); exit('Lead tidak ditemukan / bukan milik Anda.'); }
    if (in_array($lead['status'], ['won', 'lost'], true)) { flash('warning', 'Lead sudah ditutup.'); redirect('admin/lead_view.php?id=' . $leadId); }
}
$units = $pdo->prepare(
    "SELECT u.id, u.vin, u.variant_id, CONCAT(b.name,' ',v.name) AS label, c.name AS color, vp.price
       FROM vehicle_units u JOIN variants v ON v.id=u.variant_id JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id
       LEFT JOIN variant_colors c ON c.id=u.color_id LEFT JOIN variant_prices vp ON vp.variant_id=v.id AND vp.region = ?
      WHERE u.status = 'ready' ORDER BY label, u.vin");
$units->execute([cfg('app', 'region')]); $units = $units->fetchAll();
$partners = $pdo->query('SELECT id, name, min_dp_percent FROM leasing_partners WHERE is_active=1 ORDER BY name')->fetchAll();
$tenors = TdpCalculator::TENORS;

$f = ['customer_name' => $lead['name'] ?? '', 'phone' => $lead['phone'] ?? '', 'nik' => '', 'address' => '', 'unit_id' => 0, 'payment_method' => 'cash',
      'partner_id' => 0, 'tenor' => 36, 'dp_percent' => 25, 'insurance_type' => 'TLO', 'addm' => 1, 'booking_fee' => 5000000];

if (is_post()) {
    $f = ['customer_name' => input('customer_name'), 'phone' => preg_replace('/[\s-]/', '', input('phone')), 'nik' => preg_replace('/\D/', '', input('nik')),
          'address' => input('address'), 'unit_id' => input_int('unit_id'), 'payment_method' => input('payment_method'),
          'partner_id' => input_int('partner_id'), 'tenor' => input_int('tenor'), 'dp_percent' => input_float('dp_percent'),
          'insurance_type' => input('insurance_type'), 'addm' => input('addm') === '0' ? 0 : 1,
          'booking_fee' => (float)preg_replace('/[^\d]/', '', input('booking_fee'))];
    $err = [];
    if (mb_strlen($f['customer_name']) < 3) $err[] = 'Nama pembeli minimal 3 karakter.';
    if (!preg_match('/^\d{16}$/', $f['nik'])) $err[] = 'NIK harus 16 digit.';
    if (!valid_phone($f['phone'])) $err[] = 'Nomor HP tidak valid.';
    if (mb_strlen($f['address']) < 10) $err[] = 'Alamat terlalu singkat.';
    if (!in_array($f['payment_method'], ['cash', 'kredit'], true)) $err[] = 'Metode bayar tidak valid.';
    if ($f['payment_method'] === 'kredit' && !in_array($f['insurance_type'], ['TLO', 'AR'], true)) $err[] = 'Jenis asuransi tidak valid.';
    if (!$err) {
        try {
            $spkId = SpkService::create($pdo, $user, [
                'unit_id' => $f['unit_id'], 'lead_id' => $leadId ?: null, 'customer_name' => $f['customer_name'], 'nik' => $f['nik'],
                'phone' => $f['phone'], 'address' => $f['address'], 'payment_method' => $f['payment_method'], 'partner_id' => $f['partner_id'],
                'tenor' => $f['tenor'], 'dp_percent' => $f['dp_percent'], 'insurance_type' => $f['insurance_type'], 'addm' => $f['addm'],
                'booking_fee' => $f['booking_fee'],
            ]);
            flash('success', 'SPK berhasil dibuat dan unit dikunci untuk pelanggan ini.');
            redirect('admin/spk_view.php?id=' . $spkId);
        } catch (DomainException | InvalidArgumentException $ex) { flash('danger', $ex->getMessage()); }
          catch (PDOException $ex) { error_log($ex->getMessage()); flash('danger', 'Terjadi kesalahan database. Coba lagi.'); }
    } else flash('danger', implode(' ', $err));
}
$pageTitle = 'Buat SPK';
include BASE_PATH . '/app/views/header.php';
?>
<a href="<?= e(url($leadId ? 'admin/lead_view.php?id=' . $leadId : 'admin/spk_list.php')) ?>" class="small text-decoration-none text-secondary">&larr; Kembali</a>
<h4 class="my-3">Surat Pemesanan Kendaraan (SPK)</h4>
<form method="post" class="card p-4"><?= csrf_field() ?><input type="hidden" name="lead_id" value="<?= $leadId ?>">
  <h6 class="text-gold">1. Unit Kendaraan (VIN)</h6>
  <div class="mb-3"><select name="unit_id" class="form-select" required><option value="">— Pilih unit Ready —</option>
    <?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $f['unit_id'] == $u['id'] ? 'selected' : '' ?>><?= e($u['label']) ?> · <?= e($u['color'] ?? '-') ?> · <?= e($u['vin']) ?> · <?= $u['price'] ? e(rupiah($u['price'])) : 'harga belum diatur' ?></option><?php endforeach; ?></select>
    <div class="form-text">Unit dikunci otomatis saat SPK disimpan. Bila sales lain lebih dulu memilih VIN yang sama, sistem akan menolak.</div></div>
  <h6 class="text-gold mt-2">2. Identitas Pembeli</h6>
  <div class="row g-3 mb-3">
    <div class="col-md-6"><label class="form-label">Nama sesuai KTP</label><input name="customer_name" class="form-control" required maxlength="100" value="<?= e($f['customer_name']) ?>"></div>
    <div class="col-md-3"><label class="form-label">NIK</label><input name="nik" class="form-control" required maxlength="16" inputmode="numeric" value="<?= e($f['nik']) ?>"></div>
    <div class="col-md-3"><label class="form-label">No. HP</label><input name="phone" class="form-control" required value="<?= e($f['phone']) ?>"></div>
    <div class="col-12"><label class="form-label">Alamat</label><input name="address" class="form-control" required maxlength="255" value="<?= e($f['address']) ?>"></div></div>
  <h6 class="text-gold">3. Pembayaran</h6>
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Metode</label><select name="payment_method" id="pm" class="form-select"><option value="cash" <?= $f['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option><option value="kredit" <?= $f['payment_method'] === 'kredit' ? 'selected' : '' ?>>Kredit (Leasing)</option></select></div>
    <div class="col-md-3"><label class="form-label">Booking Fee (Rp)</label><input name="booking_fee" class="form-control" required value="<?= e((int)$f['booking_fee']) ?>"></div>
  </div>
  <div id="creditFields" class="row g-3 mb-3 border rounded p-2 mx-0">
    <div class="col-md-3"><label class="form-label">Mitra Leasing</label><select name="partner_id" class="form-select"><?php foreach ($partners as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $f['partner_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (DP min <?= e($p['min_dp_percent']) ?>%)</option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">Tenor</label><select name="tenor" class="form-select"><?php foreach ($tenors as $t): ?><option value="<?= $t ?>" <?= $f['tenor'] == $t ? 'selected' : '' ?>><?= $t ?> bln</option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">DP (%)</label><input type="number" step="0.5" name="dp_percent" class="form-control" value="<?= e($f['dp_percent']) ?>"></div>
    <div class="col-md-2"><label class="form-label">Asuransi</label><select name="insurance_type" class="form-select"><option value="TLO" <?= $f['insurance_type'] === 'TLO' ? 'selected' : '' ?>>TLO</option><option value="AR" <?= $f['insurance_type'] === 'AR' ? 'selected' : '' ?>>All Risk</option></select></div>
    <div class="col-md-3"><label class="form-label">Angsuran 1</label><select name="addm" class="form-select"><option value="1" <?= $f['addm'] ? 'selected' : '' ?>>ADDM (di muka)</option><option value="0" <?= !$f['addm'] ? 'selected' : '' ?>>ADDB (di belakang)</option></select></div>
    <div class="col-12 form-text">TDP & angsuran dihitung otomatis oleh server dari parameter leasing saat SPK disimpan.</div>
  </div>
  <button class="btn btn-gold">Terbitkan SPK</button>
</form>
<script>
const pm = document.getElementById('pm'), cf = document.getElementById('creditFields');
const tog = () => { const on = pm.value === 'kredit'; cf.style.display = on ? '' : 'none'; cf.querySelectorAll('input,select').forEach(i => i.disabled = !on); };
pm.addEventListener('change', tog); tog();
</script>
<?php include BASE_PATH . '/app/views/footer.php';
