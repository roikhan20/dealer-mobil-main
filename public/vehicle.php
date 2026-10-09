<?php
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$st = $pdo->prepare(
    'SELECT v.*, m.name AS model, m.body_type, b.name AS brand, vp.price
       FROM variants v JOIN models m ON m.id = v.model_id JOIN brands b ON b.id = m.brand_id
       JOIN variant_prices vp ON vp.variant_id = v.id AND vp.region = ?
      WHERE v.id = ? AND v.is_active = 1'
);
$st->execute([cfg('app', 'region'), input_int('id')]);
$v = $st->fetch();
if (!$v) { http_response_code(404); exit('Kendaraan tidak ditemukan.'); }

$colors = $pdo->prepare('SELECT name, hex FROM variant_colors WHERE variant_id = ?'); $colors->execute([$v['id']]); $colors = $colors->fetchAll();
$imgs = $pdo->prepare('SELECT path FROM variant_images WHERE variant_id = ? ORDER BY sort, id'); $imgs->execute([$v['id']]); $imgs = $imgs->fetchAll();
$stock = $pdo->prepare("SELECT COUNT(*) FROM vehicle_units WHERE variant_id = ? AND status = 'ready'"); $stock->execute([$v['id']]); $stock = (int)$stock->fetchColumn();

$pageTitle = $v['brand'] . ' ' . $v['name'];
$extraScripts = [url('assets/js/credit-calc.js')];
include BASE_PATH . '/app/views/header.php';
?>
<a href="<?= e(url('index.php')) ?>" class="text-secondary small text-decoration-none">&larr; Kembali ke katalog</a>
<div class="row g-4 mt-1">
  <div class="col-lg-7">
    <?php
      // Foto: upload admin dulu; bila belum ada, pakai foto bawaan (Wikimedia Commons / salinan lokal).
      $photos = $imgs ? array_map(fn($im) => ['src' => url('uploads/vehicles/' . $im['path']), 'credit' => null], $imgs)
                      : car_photos($v['brand'], $v['model'], $v['name']);
    ?>
    <?php if ($photos): ?>
    <div class="card overflow-hidden">
      <div id="gal" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000"><div class="carousel-inner">
        <?php foreach ($photos as $i => $ph): ?><div class="carousel-item <?= $i ? '' : 'active' ?>">
          <img class="car-img" src="<?= e($ph['src']) ?>" alt="<?= e($v['brand'] . ' ' . $v['name'] . ' foto ' . ($i + 1)) ?>" <?= $i ? 'loading="lazy"' : '' ?>>
          <?php if ($ph['credit']): ?><a class="photo-credit position-absolute bottom-0 end-0 m-2 badge text-bg-dark text-decoration-none" href="<?= e($ph['credit']) ?>" target="_blank" rel="noopener">Sumber foto: Wikimedia Commons</a><?php endif; ?>
        </div><?php endforeach; ?></div>
        <?php if (count($photos) > 1): ?>
        <button class="carousel-control-prev" type="button" data-bs-target="#gal" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span><span class="visually-hidden">Sebelumnya</span></button>
        <button class="carousel-control-next" type="button" data-bs-target="#gal" data-bs-slide="next"><span class="carousel-control-next-icon"></span><span class="visually-hidden">Berikutnya</span></button>
        <?php endif; ?>
      </div>
      <?php if (count($photos) > 1): ?>
      <div class="gal-thumbs" id="galThumbs">
        <?php foreach ($photos as $i => $ph): ?><button type="button" class="<?= $i ? '' : 'active' ?>" data-bs-target="#gal" data-bs-slide-to="<?= $i ?>" aria-label="Foto <?= $i + 1 ?>"><img class="car-img" src="<?= e($ph['src']) ?>" alt="" loading="lazy"></button><?php endforeach; ?>
      </div>
      <script>document.getElementById('gal').addEventListener('slid.bs.carousel', (e) => { document.querySelectorAll('#galThumbs button').forEach((b, i) => b.classList.toggle('active', i === e.to)); });</script>
      <?php endif; ?>
    </div>
    <?php else: ?><div class="card"><div class="car-img-ph"><i class="bi bi-car-front-fill"></i></div></div><?php endif; ?>
  </div>
  <div class="col-lg-5">
    <div class="text-secondary"><?= e($v['brand']) ?> · <?= e($v['model']) ?> · <?= e($v['body_type']) ?></div>
    <h2><?= e($v['name']) ?></h2>
    <div class="price fs-3 mb-1"><?= rupiah($v['price']) ?></div>
    <div class="small text-secondary mb-3">Harga OTR <?= e(cfg('app', 'region')) ?></div>
    <p><?= nl2br(e($v['description'])) ?></p>
    <div class="mb-3"><strong class="small">Pilihan warna:</strong><br>
      <?php foreach ($colors as $c): ?><span class="me-3 small"><span class="swatch" style="background:<?= e($c['hex']) ?>"></span> <?= e($c['name']) ?></span><?php endforeach; ?></div>
    <div class="mb-3"><?= $stock ? '<span class="badge text-bg-success">Ready stock: ' . $stock . ' unit</span>' : '<span class="badge text-bg-secondary">Indent</span>' ?></div>
    <div class="d-grid gap-2">
      <button class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#creditModal" data-price="<?= e($v['price']) ?>" data-name="<?= e($v['brand'] . ' ' . $v['name']) ?>" data-quote-url="<?= e(url('quotation.php?variant=' . (int)$v['id'])) ?>"><i class="bi bi-calculator"></i> Simulasi Kredit</button>
      <a class="btn btn-outline-light" href="<?= e(url('testdrive.php?variant=' . (int)$v['id'])) ?>"><i class="bi bi-speedometer2"></i> Booking Test Drive</a>
      <a class="btn btn-outline-gold" href="<?= e(url('quotation.php?variant=' . (int)$v['id'])) ?>"><i class="bi bi-chat-dots"></i> Minta Penawaran Harga</a>
    </div>
  </div>
</div>
<div class="card p-4 mt-4"><h5>Spesifikasi Teknis</h5>
  <table class="table table-sm mb-0">
    <tr><td>Transmisi</td><td><?= e($v['transmission']) ?></td></tr>
    <tr><td>Bahan bakar</td><td><?= e($v['fuel']) ?></td></tr>
    <?php if ($v['engine_cc']): ?><tr><td>Kapasitas mesin</td><td><?= (int)$v['engine_cc'] ?> cc</td></tr><?php endif; ?>
    <tr><td>Jumlah kursi</td><td><?= (int)$v['seats'] ?></td></tr>
    <?php foreach (preg_split('/\R|\\\\n/', (string)$v['specs'], -1, PREG_SPLIT_NO_EMPTY) as $line): [$k, $val] = array_pad(explode(':', $line, 2), 2, ''); ?>
      <tr><td><?= e(trim($k)) ?></td><td><?= e(trim($val)) ?></td></tr><?php endforeach; ?>
  </table></div>
<?php
$rel = $pdo->prepare('SELECT v.id, v.name, m.name AS model, b.name AS brand, vp.price,
        (SELECT path FROM variant_images i WHERE i.variant_id = v.id ORDER BY i.sort, i.id LIMIT 1) AS img
   FROM variants v JOIN models m ON m.id = v.model_id JOIN brands b ON b.id = m.brand_id
   JOIN variant_prices vp ON vp.variant_id = v.id AND vp.region = ?
  WHERE v.is_active = 1 AND v.id <> ? AND b.name = ? ORDER BY ABS(vp.price - ?) LIMIT 4');
$rel->execute([cfg('app', 'region'), $v['id'], $v['brand'], $v['price']]); $rel = $rel->fetchAll();
if ($rel): ?>
<h5 class="mt-5 mb-3">Mobil <?= e($v['brand']) ?> lain di kisaran harga serupa</h5>
<div class="row g-3">
  <?php foreach ($rel as $r): $cv = car_cover($r['img'], $r['brand'], $r['model'], $r['name']); ?>
  <div class="col-6 col-lg-3"><a class="card car-card h-100 text-decoration-none text-reset" href="<?= e(url('vehicle.php?id=' . (int)$r['id'])) ?>">
    <?php if ($cv): ?><img class="car-img" src="<?= e($cv) ?>" alt="<?= e($r['name']) ?>" loading="lazy"><?php else: ?><div class="car-img-ph"><i class="bi bi-car-front-fill"></i></div><?php endif; ?>
    <div class="p-2"><div class="small fw-semibold"><?= e($r['name']) ?></div><div class="small text-gold"><?= rupiah($r['price']) ?></div></div></a></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php include BASE_PATH . '/app/views/credit_modal.php'; include BASE_PATH . '/app/views/footer.php';
