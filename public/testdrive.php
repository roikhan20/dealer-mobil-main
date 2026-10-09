<?php
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$variants = $pdo->query('SELECT v.id, CONCAT(b.name," ",v.name) AS label, b.name AS brand, m.name AS model, v.name,
        (SELECT path FROM variant_images i WHERE i.variant_id = v.id ORDER BY i.sort, i.id LIMIT 1) AS img
   FROM variants v JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id WHERE v.is_active=1 ORDER BY label')->fetchAll();
$slots = ['09:00','10:00','11:00','13:00','14:00','15:00','16:00'];

if (is_post()) {
    $d = ['name' => input('name'), 'phone' => preg_replace('/[\s-]/', '', input('phone')), 'email' => input('email'),
          'variant_id' => input_int('variant_id'), 'source' => 'test_drive', 'note' => input('note')];
    $date = input('date'); $time = input('time');
    $err = [];
    if (mb_strlen($d['name']) < 3) $err[] = 'Nama minimal 3 karakter.';
    if (!valid_phone($d['phone'])) $err[] = 'Nomor HP tidak valid.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Email tidak valid.';
    if (!in_array($d['variant_id'], array_map('intval', array_column($variants, 'id')), true)) $err[] = 'Pilih mobil.';
    if (!valid_date($date) || $date < date('Y-m-d') || $date > date('Y-m-d', strtotime('+60 days'))) $err[] = 'Tanggal harus dalam 60 hari ke depan.';
    if (!in_array($time, $slots, true)) $err[] = 'Jam tidak valid.';

    $photo = null;
    if (!$err) {
        try { $photo = Uploader::saveImage($_FILES['id_photo'] ?? [], BASE_PATH . '/storage/uploads/ids'); }
        catch (RuntimeException $ex) { $err[] = 'Foto KTP/SIM A: ' . $ex->getMessage(); }
    }
    if ($err) { flash('danger', implode(' ', $err)); }
    else {
        $pdo->beginTransaction();
        try {
            $leadId = LeadService::create($pdo, $d);     // ikut transaksi yang sama
            $pdo->prepare('INSERT INTO test_drives(lead_id, variant_id, scheduled_date, scheduled_time, id_photo) VALUES (?,?,?,?,?)')
                ->execute([$leadId, $d['variant_id'], $date, $time . ':00', $photo]);
            $pdo->commit();
            flash('success', 'Booking test drive diterima. Sales kami akan mengonfirmasi jadwal Anda.');
            redirect('index.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            @unlink(BASE_PATH . '/storage/uploads/ids/' . $photo);
            flash('danger', 'Terjadi kesalahan, silakan coba lagi.');
        }
    }
}
$pageTitle = 'Booking Test Drive';
include BASE_PATH . '/app/views/header.php';
$sel = input_int('variant', (int)($_POST['variant_id'] ?? 0));
?>
<div class="row justify-content-center"><div class="col-lg-7"><div class="card p-4">
  <h4><i class="bi bi-speedometer2 text-gold"></i> Booking Test Drive</h4>
  <p class="text-secondary small">Unggah foto KTP/SIM A untuk verifikasi saat uji kendara. Data Anda disimpan privat.</p>
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Mobil</label><select name="variant_id" id="carSel" class="form-select" required><option value="">— Pilih —</option>
      <?php foreach ($variants as $v): ?><option value="<?= (int)$v['id'] ?>" data-photo="<?= e((string)car_cover($v['img'], $v['brand'], $v['model'], $v['name'])) ?>" <?= $sel == $v['id'] ? 'selected' : '' ?>><?= e($v['label']) ?></option><?php endforeach; ?></select></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Nama lengkap</label><input name="name" class="form-control" required maxlength="100" value="<?= e(input('name')) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">No. HP</label><input name="phone" class="form-control" required placeholder="08xxxxxxxxxx" value="<?= e(input('phone')) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Email (opsional)</label><input type="email" name="email" class="form-control" value="<?= e(input('email')) ?>"></div>
      <div class="col-md-3 mb-3"><label class="form-label">Tanggal</label><input type="date" name="date" class="form-control" required min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>" value="<?= e(input('date')) ?>"></div>
      <div class="col-md-3 mb-3"><label class="form-label">Jam</label><select name="time" class="form-select" required><?php foreach ($slots as $s): ?><option <?= input('time') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="mb-3"><label class="form-label">Foto KTP / SIM A <small class="text-secondary">(JPG/PNG/WEBP, maks 3 MB)</small></label>
      <input type="file" name="id_photo" class="form-control" accept="image/jpeg,image/png,image/webp" required></div>
    <button class="btn btn-gold w-100">Kirim Booking</button>
  </form></div></div></div>
<script>
(() => {
  const sel = document.getElementById('carSel'); if (!sel) return;
  const box = document.createElement('div'); box.className = 'mt-2'; sel.parentNode.appendChild(box);
  const show = () => { const src = sel.selectedOptions[0]?.dataset.photo;
    box.innerHTML = src ? '<img class="car-img rounded" style="max-height:220px" alt="">' : '';
    if (src) box.querySelector('img').src = src; };
  sel.addEventListener('change', show); show();
})();
</script>
<?php include BASE_PATH . '/app/views/footer.php';
