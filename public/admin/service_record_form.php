<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('service_advisor'); $area = 'staff'; $pdo = db();
$bookingId = input_int('booking_id'); $booking = null;
$f = ['vin' => '', 'service_date' => date('Y-m-d'), 'odometer' => '', 'complaint' => '', 'work_done' => '', 'warranty_claim' => 0];
if ($bookingId) {
    $b = $pdo->prepare("SELECT * FROM service_bookings WHERE id = ? AND status NOT IN ('done','cancelled')"); $b->execute([$bookingId]); $booking = $b->fetch();
    if (!$booking) { flash('warning', 'Booking tidak ditemukan atau sudah selesai.'); redirect('admin/services.php'); }
    $f['vin'] = $booking['vin']; $f['complaint'] = (string)$booking['complaint']; $f['service_date'] = $booking['service_date'];
}
if (is_post()) {
    $f = ['vin' => $booking ? $booking['vin'] : strtoupper(preg_replace('/\s/', '', input('vin'))), 'service_date' => input('service_date'),
          'odometer' => input_int('odometer'), 'complaint' => input('complaint'), 'work_done' => input('work_done'), 'warranty_claim' => isset($_POST['warranty_claim']) ? 1 : 0];
    $parts = [];
    foreach ((array)($_POST['part_name'] ?? []) as $i => $pn) {
        $pn = trim(strip_tags((string)$pn)); $q = max(1, min(99, (int)($_POST['part_qty'][$i] ?? 1)));
        if ($pn !== '') $parts[] = [mb_substr($pn, 0, 120), $q];
    }
    $err = [];
    if (!preg_match(VIN_REGEX, $f['vin'])) $err[] = 'VIN tidak valid.';
    if (!valid_date($f['service_date']) || $f['service_date'] > date('Y-m-d')) $err[] = 'Tanggal servis tidak valid.';
    if ($f['odometer'] <= 0 || $f['odometer'] > 999999) $err[] = 'Kilometer tidak valid.';
    if (mb_strlen($f['work_done']) < 3) $err[] = 'Pekerjaan yang dilakukan wajib diisi.';
    if (!$err) {
        $c = $pdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE vin = ?'); $c->execute([$f['vin']]);
        if (!$c->fetchColumn()) $err[] = 'VIN tidak terdaftar.';
    }
    if ($err) flash('danger', implode(' ', $err));
    else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO service_records(vin,booking_id,advisor_id,service_date,odometer,complaint,work_done,warranty_claim) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$f['vin'], $bookingId ?: null, $user['id'], $f['service_date'], $f['odometer'], $f['complaint'] ?: null, $f['work_done'], $f['warranty_claim']]);
            $rid = (int)$pdo->lastInsertId();
            $ip = $pdo->prepare('INSERT INTO service_parts(record_id,part_name,qty) VALUES (?,?,?)');
            foreach ($parts as [$pn, $q]) $ip->execute([$rid, $pn, $q]);
            if ($bookingId) $pdo->prepare("UPDATE service_bookings SET status = 'done' WHERE id = ?")->execute([$bookingId]);
            $pdo->commit();
            flash('success', 'Riwayat servis tersimpan.');
            redirect('admin/services.php');
        } catch (PDOException $ex) { $pdo->rollBack(); error_log($ex->getMessage()); flash('danger', 'Gagal menyimpan riwayat servis.'); }
    }
}
$pageTitle = 'Catat Riwayat Servis';
include BASE_PATH . '/app/views/header.php';
?>
<a href="<?= e(url('admin/services.php')) ?>" class="small text-decoration-none text-secondary">&larr; Slot servis</a>
<h4 class="my-3">Catat Riwayat Servis <?= $booking ? '<small class="text-secondary fs-6">' . e($booking['booking_code']) . '</small>' : '' ?></h4>
<form method="post" class="card p-4"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= $bookingId ?>">
  <div class="row g-3">
    <div class="col-md-5"><label class="form-label">VIN</label><input name="vin" class="form-control text-uppercase" maxlength="17" required <?= $booking ? 'readonly' : '' ?> value="<?= e($f['vin']) ?>"></div>
    <div class="col-md-3"><label class="form-label">Tanggal servis</label><input type="date" name="service_date" max="<?= date('Y-m-d') ?>" class="form-control" required value="<?= e($f['service_date']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Odometer (km)</label><input type="number" name="odometer" class="form-control" required value="<?= e($f['odometer']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Keluhan</label><textarea name="complaint" rows="3" class="form-control"><?= e($f['complaint']) ?></textarea></div>
    <div class="col-md-6"><label class="form-label">Pekerjaan dilakukan</label><textarea name="work_done" rows="3" class="form-control" required><?= e($f['work_done']) ?></textarea></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="warranty_claim" id="wc" <?= $f['warranty_claim'] ? 'checked' : '' ?>><label for="wc" class="form-check-label">Klaim garansi</label></div></div>
    <div class="col-12"><label class="form-label">Penggantian spare part</label><div id="parts"></div>
      <button type="button" class="btn btn-sm btn-outline-gold mt-2" id="addPart"><i class="bi bi-plus"></i> Tambah part</button></div>
  </div>
  <div class="mt-4"><button class="btn btn-gold">Simpan<?= $booking ? ' &amp; Tandai Selesai' : '' ?></button></div>
</form>
<script>
const box = document.getElementById('parts');
function row() { const d = document.createElement('div'); d.className = 'row g-2 mb-2';
  d.innerHTML = '<div class="col-8"><input name="part_name[]" class="form-control form-control-sm" maxlength="120" placeholder="Nama part"></div><div class="col-2"><input type="number" name="part_qty[]" class="form-control form-control-sm" value="1" min="1" max="99"></div><div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger w-100">×</button></div>';
  d.querySelector('button').onclick = () => d.remove(); box.appendChild(d); }
document.getElementById('addPart').onclick = row; row();
</script>
<?php include BASE_PATH . '/app/views/footer.php';
