<?php
/** Pelanggan mengecek status permintaan (lead / test drive / SPK) dengan kode referensi LD-xxxxxx + 4 digit akhir HP. */
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$ref = strtoupper(trim(input('ref')));
$tail = preg_replace('/\D/', '', input('tail'));
$lead = null; $tds = []; $spk = null;

$leadLabel = [
    'cold' => 'Diterima, menunggu ditangani sales',
    'warm' => 'Sedang diproses sales',
    'hot'  => 'Sedang diproses sales (prioritas)',
    'won'  => 'Pemesanan diproses (SPK telah diterbitkan)',
    'lost' => 'Ditutup',
];
$tdLabel = ['requested' => 'Menunggu konfirmasi sales', 'confirmed' => 'Terkonfirmasi', 'done' => 'Selesai', 'cancelled' => 'Dibatalkan'];

if ($ref !== '') {
    if (!preg_match('/^LD-\d{6}$/', $ref) || strlen($tail) !== 4) {
        flash('danger', 'Masukkan kode referensi (contoh LD-000012) dan 4 digit terakhir nomor HP Anda.');
    } else {
        $id = (int)substr($ref, 3);
        $st = $pdo->prepare(
            "SELECT l.id, l.name, l.phone, l.status, CONCAT(b.name,' ',v.name) AS car, u.name AS sales_name
               FROM leads l
               LEFT JOIN variants v ON v.id = l.variant_id
               LEFT JOIN models m ON m.id = v.model_id
               LEFT JOIN brands b ON b.id = m.brand_id
               LEFT JOIN users u ON u.id = l.sales_id
              WHERE l.id = ?"
        );
        $st->execute([$id]);
        $lead = $st->fetch();
        if (!$lead || substr(preg_replace('/\D/', '', $lead['phone']), -4) !== $tail) {
            $lead = null;
            flash('warning', 'Data tidak ditemukan. Periksa kembali kode referensi dan nomor HP Anda.');
        } else {
            $t = $pdo->prepare('SELECT * FROM test_drives WHERE lead_id = ? ORDER BY id DESC');
            $t->execute([$id]); $tds = $t->fetchAll();
            $s = $pdo->prepare("SELECT spk_no, status FROM spk WHERE lead_id = ? ORDER BY id DESC LIMIT 1");
            $s->execute([$id]); $spk = $s->fetch() ?: null;
        }
    }
}
$pageTitle = 'Cek Status Permintaan';
include BASE_PATH . '/app/views/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-7">
  <div class="card p-4 mb-4">
    <h4><i class="bi bi-ticket-detailed text-gold"></i> Cek Status Permintaan</h4>
    <p class="text-secondary small">Gunakan kode referensi yang Anda terima saat mengirim permintaan pemesanan, penawaran, atau test drive.</p>
    <form method="get" class="row g-2">
      <div class="col-md-6"><label class="form-label small">Kode referensi</label><input name="ref" class="form-control text-uppercase" placeholder="LD-000012" value="<?= e($ref) ?>" required></div>
      <div class="col-md-3"><label class="form-label small">4 digit akhir No. HP</label><input name="tail" class="form-control" inputmode="numeric" maxlength="4" pattern="\d{4}" value="<?= e($tail) ?>" required></div>
      <div class="col-md-3 d-grid align-self-end"><button class="btn btn-gold"><i class="bi bi-search"></i> Cek</button></div>
    </form>
  </div>

<?php if ($lead): ?>
  <div class="card p-4">
    <div class="d-flex justify-content-between flex-wrap gap-2">
      <div><h5 class="mb-0"><?= e($lead['car'] ?: 'Permintaan umum') ?></h5><div class="small text-secondary">Kode <?= e(LeadService::ref((int)$lead['id'])) ?> · a.n. <?= e($lead['name']) ?></div></div>
      <div><span class="badge text-bg-light"><?= e($leadLabel[$lead['status']] ?? $lead['status']) ?></span></div>
    </div>
    <hr>
    <table class="table table-sm mb-0">
      <tr><td>Status permintaan</td><td><?= e($leadLabel[$lead['status']] ?? $lead['status']) ?></td></tr>
      <tr><td>Sales penanggung jawab</td><td><?= e($lead['sales_name'] ?: 'Sedang ditugaskan') ?></td></tr>
      <?php if ($spk): ?><tr><td>Surat pemesanan (SPK)</td><td><?= e($spk['spk_no']) ?> · <?= e(strtoupper($spk['status'])) ?></td></tr><?php endif; ?>
    </table>
    <?php if ($tds): ?>
      <h6 class="mt-3">Jadwal test drive</h6>
      <table class="table table-sm mb-0">
        <?php foreach ($tds as $t): ?>
          <tr><td><?= e(date('d M Y', strtotime($t['scheduled_date']))) ?>, <?= e(substr($t['scheduled_time'], 0, 5)) ?></td><td><?= e($tdLabel[$t['status']] ?? $t['status']) ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    <p class="small text-secondary mt-3 mb-0">Butuh bantuan cepat? Hubungi showroom atau balas pesan WhatsApp dari sales Anda.</p>
  </div>
<?php endif; ?>
</div></div>
<?php include BASE_PATH . '/app/views/footer.php';
