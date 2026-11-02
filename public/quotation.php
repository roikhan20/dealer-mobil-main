<?php
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$region = cfg('app', 'region');

$variants = $pdo->query('SELECT v.id, CONCAT(b.name," ",v.name) AS label, b.name AS brand, m.name AS model, v.name,
        (SELECT path FROM variant_images i WHERE i.variant_id = v.id ORDER BY i.sort, i.id LIMIT 1) AS img
   FROM variants v JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id WHERE v.is_active=1 ORDER BY label')->fetchAll();

// Warna per varian (pelanggan memilih warna sebelum memesan)
$colorsByVariant = [];
foreach ($pdo->query('SELECT id, variant_id, name FROM variant_colors ORDER BY id')->fetchAll() as $c) {
    $colorsByVariant[(int)$c['variant_id']][] = ['id' => (int)$c['id'], 'name' => $c['name']];
}
$partners = $pdo->query('SELECT id, name, min_dp_percent FROM leasing_partners WHERE is_active=1 ORDER BY name')->fetchAll();
$tenors = TdpCalculator::TENORS;
$variantIds = array_map('intval', array_column($variants, 'id'));

if (is_post()) {
    $d = ['name' => input('name'), 'phone' => normalize_phone(input('phone')), 'email' => input('email'),
          'variant_id' => input_int('variant_id'), 'source' => 'quotation', 'note' => input('note')];
    $pay       = input('pay') === 'kredit' ? 'kredit' : 'tunai';
    $colorId   = input_int('color_id');
    $partnerId = input_int('partner_id');
    $tenor     = input_int('tenor');
    $dp        = input_float('dp_percent');
    $ins       = input('insurance_type') === 'AR' ? 'AR' : 'TLO';
    $addm      = input('timing') !== 'ADDB';

    $err = [];
    if (mb_strlen($d['name']) < 3) $err[] = 'Nama minimal 3 karakter.';
    if (!valid_phone($d['phone'])) $err[] = 'Nomor HP/WhatsApp tidak valid (contoh 08xxxxxxxxxx).';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Email tidak valid.';

    $validVariant = in_array($d['variant_id'], $variantIds, true);
    if (!$validVariant) $err[] = 'Pilih kendaraan.';

    $colorName = '';
    if ($validVariant) {
        $opts = $colorsByVariant[$d['variant_id']] ?? [];
        if ($opts) {
            $hit = array_values(array_filter($opts, fn($o) => $o['id'] === $colorId));
            if (!$hit) $err[] = 'Pilih warna kendaraan.';
            else $colorName = $hit[0]['name'];
        }
    }

    // Harga OTR dan simulasi kredit SELALU dihitung ulang di server dari database.
    $summary = '';
    if ($validVariant) {
        $pr = $pdo->prepare('SELECT price FROM variant_prices WHERE variant_id = ? AND region = ?');
        $pr->execute([$d['variant_id'], $region]); $otr = (float)$pr->fetchColumn();
        if ($otr <= 0) {
            $err[] = 'Harga kendaraan belum tersedia. Silakan hubungi showroom.';
        } elseif ($pay === 'kredit') {
            try {
                $calc = TdpCalculator::forPartner($pdo, $partnerId, $tenor, $otr, $dp, $ins, $addm);
                $summary = sprintf(
                    'Pembayaran KREDIT. Mitra: %s, DP %s%% (%s), tenor %d bln, asuransi %s, angsuran %s. Estimasi angsuran %s/bln, estimasi TDP %s.',
                    $pdo->query('SELECT name FROM leasing_partners WHERE id = ' . (int)$partnerId)->fetchColumn() ?: '-',
                    rtrim(rtrim(number_format($dp, 2, ',', ''), '0'), ','), rupiah($calc['dp']),
                    $tenor, $ins, $addm ? 'ADDM' : 'ADDB', rupiah($calc['installment']), rupiah($calc['tdp'])
                );
            } catch (InvalidArgumentException $ex) {
                $err[] = $ex->getMessage();
            }
        } else {
            $summary = 'Pembayaran TUNAI (cash).';
        }
    }

    if (!$err) {
        // Cegah permintaan ganda: nomor & mobil yang sama masih punya lead terbuka dalam 24 jam terakhir.
        $dup = $pdo->prepare("SELECT id FROM leads WHERE phone = ? AND variant_id = ? AND status IN ('cold','warm','hot')
                               AND created_at > NOW() - INTERVAL 1 DAY ORDER BY id DESC LIMIT 1");
        $dup->execute([$d['phone'], $d['variant_id']]);
        if ($existing = $dup->fetchColumn()) {
            $err[] = 'Permintaan untuk mobil ini sudah kami terima (kode ' . LeadService::ref((int)$existing) . '). Sales kami akan segera menghubungi Anda.';
        }
    }

    if ($err) { flash('danger', implode(' ', $err)); }
    else {
        $lines = array_filter([
            $summary,
            $colorName ? 'Warna: ' . $colorName . '.' : '',
            $d['note'] !== '' ? 'Catatan pelanggan: ' . $d['note'] : '',
        ]);
        $d['note'] = implode("\n", $lines);
        $leadId = LeadService::create($pdo, $d);
        $ref = LeadService::ref($leadId);
        flash('success', 'Permintaan Anda diterima. Kode referensi: ' . $ref . '. Simpan kode ini dan 4 digit terakhir nomor HP Anda untuk cek status di menu "Cek Status". Sales kami akan menghubungi Anda via WhatsApp.');
        redirect('index.php');
    }
}

$pageTitle = 'Pesan Mobil / Minta Penawaran';
include BASE_PATH . '/app/views/header.php';

$sel = input_int('variant', (int)($_POST['variant_id'] ?? 0));
$pay = input('pay') === 'kredit' ? 'kredit' : 'tunai';
$partnerSel = input_int('partner_id'); $tenorSel = input_int('tenor') ?: 36;
$dpSel = input('dp_percent') !== '' ? input_float('dp_percent') : 25;
$insSel = input('insurance_type') === 'AR' ? 'AR' : 'TLO';
$timingSel = input('timing') === 'ADDB' ? 'ADDB' : 'ADDM';
?>
<div class="row justify-content-center"><div class="col-lg-7"><div class="card p-4">
  <h4>Pesan Mobil / Minta Penawaran</h4>
  <p class="text-secondary small">Pilih mobil, warna, dan cara pembayaran. Sales kami akan menghubungi Anda untuk konfirmasi harga dan ketersediaan unit.</p>
  <form method="post" id="orderForm"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Kendaraan</label><select name="variant_id" id="carSel" class="form-select" required>
      <option value="">— Pilih —</option><?php foreach ($variants as $v): ?><option value="<?= (int)$v['id'] ?>" data-photo="<?= e((string)car_cover($v['img'], $v['brand'], $v['model'], $v['name'])) ?>" <?= $sel == $v['id'] ? 'selected' : '' ?>><?= e($v['label']) ?></option><?php endforeach; ?></select></div>

    <div class="mb-3" id="colorWrap"><label class="form-label">Warna</label><select name="color_id" id="colorSel" class="form-select"></select></div>

    <div class="mb-3"><label class="form-label d-block">Cara pembayaran</label>
      <div class="btn-group w-100">
        <input type="radio" class="btn-check" name="pay" id="pay_tunai" value="tunai" <?= $pay === 'tunai' ? 'checked' : '' ?>><label class="btn btn-outline-gold btn-sm" for="pay_tunai">Tunai</label>
        <input type="radio" class="btn-check" name="pay" id="pay_kredit" value="kredit" <?= $pay === 'kredit' ? 'checked' : '' ?>><label class="btn btn-outline-gold btn-sm" for="pay_kredit">Kredit (Leasing)</label>
      </div></div>

    <div id="creditBox" class="border rounded p-3 mb-3">
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label small">Mitra leasing</label><select name="partner_id" class="form-select">
          <?php foreach ($partners as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $partnerSel == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (DP min <?= e($p['min_dp_percent']) ?>%)</option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small">Tenor</label><select name="tenor" class="form-select">
          <?php foreach ($tenors as $t): ?><option value="<?= $t ?>" <?= $tenorSel == $t ? 'selected' : '' ?>><?= $t ?> bln</option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small">DP (%)</label><input type="number" step="0.5" min="10" max="90" name="dp_percent" class="form-control" value="<?= e($dpSel) ?>"></div>
        <div class="col-md-6"><label class="form-label small">Asuransi</label><select name="insurance_type" class="form-select">
          <option value="TLO" <?= $insSel === 'TLO' ? 'selected' : '' ?>>TLO</option><option value="AR" <?= $insSel === 'AR' ? 'selected' : '' ?>>All Risk</option></select></div>
        <div class="col-md-6"><label class="form-label small">Angsuran pertama</label><select name="timing" class="form-select">
          <option value="ADDM" <?= $timingSel === 'ADDM' ? 'selected' : '' ?>>ADDM (di muka)</option><option value="ADDB" <?= $timingSel === 'ADDB' ? 'selected' : '' ?>>ADDB (di belakang)</option></select></div>
      </div>
      <div class="form-text">Angka final mengikuti persetujuan leasing dan dihitung ulang saat SPK diterbitkan.</div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Nama lengkap</label><input name="name" class="form-control" value="<?= e(input('name')) ?>" required maxlength="100"></div>
      <div class="col-md-6 mb-3"><label class="form-label">No. WhatsApp</label><input name="phone" class="form-control" placeholder="08xxxxxxxxxx" value="<?= e(input('phone')) ?>" required></div>
    </div>
    <div class="mb-3"><label class="form-label">Email (opsional)</label><input type="email" name="email" class="form-control" value="<?= e(input('email')) ?>"></div>
    <div class="mb-3"><label class="form-label">Catatan</label><textarea name="note" rows="3" class="form-control" maxlength="500"><?= e(input('note')) ?></textarea></div>
    <button class="btn btn-gold w-100">Kirim Permintaan</button>
  </form></div></div></div>
<script>
(() => {
  const COLORS = <?= json_encode((object)$colorsByVariant, JSON_UNESCAPED_UNICODE) ?>;
  const sel = document.getElementById('carSel');
  const colorSel = document.getElementById('colorSel');
  const colorWrap = document.getElementById('colorWrap');
  const creditBox = document.getElementById('creditBox');
  const wanted = <?= json_encode(input('color_id') !== '' ? input_int('color_id') : 0) ?>;

  // Foto pratinjau mobil
  const box = document.createElement('div'); box.className = 'mt-2'; sel.parentNode.appendChild(box);
  const showPhoto = () => {
    const src = sel.selectedOptions[0]?.dataset.photo;
    box.innerHTML = src ? '<img class="car-img rounded" style="max-height:220px" alt="">' : '';
    if (src) box.querySelector('img').src = src;
  };

  // Daftar warna mengikuti varian yang dipilih
  const fillColors = () => {
    const list = COLORS[sel.value] || [];
    colorWrap.style.display = list.length ? '' : 'none';
    colorSel.innerHTML = list.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    if (list.some(c => c.id === wanted)) colorSel.value = wanted;
    colorSel.required = list.length > 0;
  };

  // Blok kredit hanya tampil bila pembayaran kredit dipilih
  const togglePay = () => {
    const on = document.getElementById('pay_kredit').checked;
    creditBox.style.display = on ? '' : 'none';
    creditBox.querySelectorAll('select,input').forEach(i => i.disabled = !on);
  };

  sel.addEventListener('change', () => { showPhoto(); fillColors(); });
  document.querySelectorAll('input[name="pay"]').forEach(r => r.addEventListener('change', togglePay));
  showPhoto(); fillColors(); togglePay();
})();
</script>
<?php include BASE_PATH . '/app/views/footer.php';
