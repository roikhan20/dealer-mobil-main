<?php
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$variants = $pdo->query('SELECT v.id, CONCAT(b.name," ",v.name) AS label, b.name AS brand, m.name AS model, v.name,
        (SELECT path FROM variant_images i WHERE i.variant_id = v.id ORDER BY i.sort, i.id LIMIT 1) AS img
   FROM variants v JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id WHERE v.is_active=1 ORDER BY label')->fetchAll();
$slots = ['09:00','10:00','11:00','13:00','14:00','15:00','16:00'];
$capacity = max(1, (int)cfg('app', 'testdrive_slot_capacity'));

if (is_post()) {
    $d = ['name' => input('name'), 'phone' => normalize_phone(input('phone')), 'email' => input('email'),
          'variant_id' => input_int('variant_id'), 'source' => 'test_drive', 'note' => input('note')];
    $date = input('date'); $time = input('time');
    $err = [];
    if (mb_strlen($d['name']) < 3) $err[] = 'Nama minimal 3 karakter.';
    if (!valid_phone($d['phone'])) $err[] = 'Nomor HP tidak valid (contoh 08xxxxxxxxxx).';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Email tidak valid.';
    if (!in_array($d['variant_id'], array_map('intval', array_column($variants, 'id')), true)) $err[] = 'Pilih mobil.';
    if (!valid_date($date) || $date < date('Y-m-d') || $date > date('Y-m-d', strtotime('+60 days'))) $err[] = 'Tanggal harus dalam 60 hari ke depan.';
    if (!in_array($time, $slots, true)) $err[] = 'Jam tidak valid.';
    // Jam hari ini yang sudah lewat tidak boleh dipesan.
    if (!$err && $date === date('Y-m-d') && strtotime($date . ' ' . $time) <= time()) {
        $err[] = 'Jam tersebut sudah lewat. Pilih jam lain atau tanggal besok.';
    }
    // Satu nomor tidak boleh punya dua jadwal aktif untuk mobil & tanggal yang sama.
    if (!$err) {
        $dup = $pdo->prepare("SELECT t.lead_id FROM test_drives t JOIN leads l ON l.id = t.lead_id
                               WHERE l.phone = ? AND t.variant_id = ? AND t.scheduled_date = ? AND t.status IN ('requested','confirmed') LIMIT 1");
        $dup->execute([$d['phone'], $d['variant_id'], $date]);
        if ($lid = $dup->fetchColumn()) {
            $err[] = 'Anda sudah memiliki jadwal test drive untuk mobil ini pada tanggal tersebut (kode ' . LeadService::ref((int)$lid) . ').';
        }
    }

    // KTP/SIM bersifat OPSIONAL saat booking: data pribadi tidak wajib dikumpulkan dari setiap pengunjung.
    // Verifikasi tetap dilakukan di showroom saat hari H bila belum diunggah.
    $photo = null;
    if (!$err && ($_FILES['id_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try { $photo = Uploader::saveImage($_FILES['id_photo'] ?? [], BASE_PATH . '/storage/uploads/ids'); }
        catch (RuntimeException $ex) { $err[] = 'Foto KTP/SIM A: ' . $ex->getMessage(); }
    }

    if ($err) {
        flash('danger', implode(' ', $err));
    } else {
        $lock = 'td_' . $date . '_' . str_replace(':', '', $time);
        $ok = false; $leadId = 0;
        $gotLock = false;
        try {
            $ls = $pdo->prepare('SELECT GET_LOCK(?, 10)'); $ls->execute([$lock]);
            $gotLock = (int)$ls->fetchColumn() === 1;
            if (!$gotLock) throw new DomainException('Sistem sedang sibuk, silakan coba lagi.');

            $pdo->beginTransaction();
            // Kapasitas per jam: hitung hanya jadwal yang masih aktif (requested/confirmed).
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM test_drives WHERE scheduled_date = ? AND scheduled_time = ? AND status IN ('requested','confirmed')");
            $cnt->execute([$date, $time . ':00']);
            if ((int)$cnt->fetchColumn() >= $capacity) {
                throw new DomainException('Jadwal jam ' . $time . ' sudah penuh. Silakan pilih jam lain.');
            }
            $leadId = LeadService::create($pdo, $d);     // ikut transaksi yang sama
            $pdo->prepare('INSERT INTO test_drives(lead_id, variant_id, scheduled_date, scheduled_time, id_photo) VALUES (?,?,?,?,?)')
                ->execute([$leadId, $d['variant_id'], $date, $time . ':00', $photo]);
            $pdo->commit();
            $ok = true;
        } catch (DomainException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            @unlink(BASE_PATH . '/storage/uploads/ids/' . $photo);
            flash('danger', $ex->getMessage());
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            @unlink(BASE_PATH . '/storage/uploads/ids/' . $photo);
            error_log('testdrive: ' . $ex->getMessage());
            flash('danger', 'Terjadi kesalahan, silakan coba lagi.');
        } finally {
            if ($gotLock) $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
        }
        if ($ok) {
            flash('success', 'Booking test drive diterima. Kode referensi: ' . LeadService::ref($leadId) . '. Simpan kode ini dan 4 digit terakhir nomor HP untuk cek status di menu "Cek Status". Sales kami akan mengonfirmasi jadwal Anda.');
            redirect('index.php');
        }
    }
}
$pageTitle = 'Booking Test Drive';
include BASE_PATH . '/app/views/header.php';
$sel = input_int('variant', (int)($_POST['variant_id'] ?? 0));
?>
<div class="row justify-content-center"><div class="col-lg-7"><div class="card p-4">
  <h4><i class="bi bi-speedometer2 text-gold"></i> Booking Test Drive</h4>
  <p class="text-secondary small">Pilih tanggal dan jam. Jumlah sesi per jam terbatas. Foto KTP/SIM A boleh diunggah sekarang atau dibawa saat hari H.</p>
  <form method="post" enctype="multipart/form-data" id="tdForm"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Mobil</label><select name="variant_id" id="carSel" class="form-select" required><option value="">— Pilih —</option>
      <?php foreach ($variants as $v): ?><option value="<?= (int)$v['id'] ?>" data-photo="<?= e((string)car_cover($v['img'], $v['brand'], $v['model'], $v['name'])) ?>" <?= $sel == $v['id'] ? 'selected' : '' ?>><?= e($v['label']) ?></option><?php endforeach; ?></select></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Nama lengkap</label><input name="name" class="form-control" required maxlength="100" value="<?= e(input('name')) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">No. HP</label><input name="phone" class="form-control" required placeholder="08xxxxxxxxxx" value="<?= e(input('phone')) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Email (opsional)</label><input type="email" name="email" class="form-control" value="<?= e(input('email')) ?>"></div>
      <div class="col-md-3 mb-3"><label class="form-label">Tanggal</label><input type="date" name="date" id="tdDate" class="form-control" required min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>" value="<?= e(input('date')) ?>"></div>
      <div class="col-md-3 mb-3"><label class="form-label">Jam</label><select name="time" id="tdTime" class="form-select" required><?php foreach ($slots as $s): ?><option <?= input('time') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="mb-3"><label class="form-label">Foto KTP / SIM A <small class="text-secondary">(opsional · JPG/PNG/WEBP, maks 3 MB)</small></label>
      <input type="file" name="id_photo" class="form-control" accept="image/jpeg,image/png,image/webp"></div>
    <button class="btn btn-gold w-100">Kirim Booking</button>
  </form></div></div></div>
<script>
(() => {
  // Foto pratinjau mobil
  const sel = document.getElementById('carSel');
  const box = document.createElement('div'); box.className = 'mt-2'; sel.parentNode.appendChild(box);
  const show = () => { const src = sel.selectedOptions[0]?.dataset.photo;
    box.innerHTML = src ? '<img class="car-img rounded" style="max-height:220px" alt="">' : '';
    if (src) box.querySelector('img').src = src; };
  sel.addEventListener('change', show); show();

  // Nonaktifkan jam yang sudah lewat bila tanggal = hari ini
  const dateEl = document.getElementById('tdDate'), timeEl = document.getElementById('tdTime');
  const todayStr = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  const refreshSlots = () => {
    const nowHM = new Date().toTimeString().slice(0, 5);
    [...timeEl.options].forEach(o => { o.disabled = dateEl.value === todayStr && o.value <= nowHM; });
    if (timeEl.selectedOptions[0]?.disabled) { const next = [...timeEl.options].find(o => !o.disabled); if (next) next.selected = true; }
  };
  dateEl.addEventListener('change', refreshSlots); refreshSlots();
})();
</script>
<?php include BASE_PATH . '/app/views/footer.php';
