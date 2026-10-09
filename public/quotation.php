<?php
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$variants = $pdo->query('SELECT v.id, CONCAT(b.name," ",v.name) AS label, b.name AS brand, m.name AS model, v.name,
        (SELECT path FROM variant_images i WHERE i.variant_id = v.id ORDER BY i.sort, i.id LIMIT 1) AS img
   FROM variants v JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id WHERE v.is_active=1 ORDER BY label')->fetchAll();

if (is_post()) {
    $d = ['name' => input('name'), 'phone' => preg_replace('/[\s-]/', '', input('phone')), 'email' => input('email'),
          'variant_id' => input_int('variant_id'), 'source' => 'quotation', 'note' => input('note')];
    $err = [];
    if (mb_strlen($d['name']) < 3) $err[] = 'Nama minimal 3 karakter.';
    if (!valid_phone($d['phone'])) $err[] = 'Nomor HP/WhatsApp tidak valid.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Email tidak valid.';
    if (!in_array($d['variant_id'], array_map('intval', array_column($variants, 'id')), true)) $err[] = 'Pilih kendaraan.';
    if ($err) { flash('danger', implode(' ', $err)); }
    else {
        LeadService::create($pdo, $d);
        flash('success', 'Terima kasih! Sales Executive kami akan menghubungi Anda segera.');
        redirect('index.php');
    }
}
$pageTitle = 'Minta Penawaran';
include BASE_PATH . '/app/views/header.php';
$sel = input_int('variant', (int)($_POST['variant_id'] ?? 0));
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="card p-4">
  <h4>Minta Penawaran</h4><p class="text-secondary small">Isi data Anda, sales kami akan menghubungi via WhatsApp/telepon.</p>
  <form method="post"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Kendaraan</label><select name="variant_id" id="carSel" class="form-select" required>
      <option value="">— Pilih —</option><?php foreach ($variants as $v): ?><option value="<?= (int)$v['id'] ?>" data-photo="<?= e((string)car_cover($v['img'], $v['brand'], $v['model'], $v['name'])) ?>" <?= $sel == $v['id'] ? 'selected' : '' ?>><?= e($v['label']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label">Nama lengkap</label><input name="name" class="form-control" value="<?= e(input('name')) ?>" required maxlength="100"></div>
    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">No. WhatsApp</label><input name="phone" class="form-control" placeholder="08xxxxxxxxxx" value="<?= e(input('phone')) ?>" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Email (opsional)</label><input type="email" name="email" class="form-control" value="<?= e(input('email')) ?>"></div></div>
    <div class="mb-3"><label class="form-label">Catatan</label><textarea name="note" rows="3" class="form-control" maxlength="500"><?= e(input('note')) ?></textarea></div>
    <button class="btn btn-gold w-100">Kirim Permintaan</button>
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
