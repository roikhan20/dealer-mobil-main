<?php
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$region = cfg('app', 'region');

// ---- Filter (semua dibind sebagai parameter) ----
$where = ['v.is_active = 1']; $p = [':region' => $region];
if (($b = input_int('brand')) > 0)           { $where[] = 'b.id = :brand'; $p[':brand'] = $b; }
if (in_array($bt = input('body'), ['SUV','MPV','Sedan','Hatchback','Pickup','Coupe'], true)) { $where[] = 'm.body_type = :body'; $p[':body'] = $bt; }
if (in_array($tr = input('trans'), ['MT','AT','CVT','DCT'], true))   { $where[] = 'v.transmission = :trans'; $p[':trans'] = $tr; }
if (in_array($fu = input('fuel'), ['Bensin','Diesel','Hybrid','Listrik'], true)) { $where[] = 'v.fuel = :fuel'; $p[':fuel'] = $fu; }
if (($mp = input_int('max_price')) > 0)      { $where[] = 'vp.price <= :mp'; $p[':mp'] = $mp; }
if (($q = input('q')) !== '')                { $where[] = 'CONCAT(b.name," ",v.name) LIKE :q'; $p[':q'] = '%' . $q . '%'; }

// ---- Urutkan (whitelist; nilai dari user tidak pernah masuk ke SQL langsung) ----
$sortOptions = [
    'price_asc'  => ['Harga termurah',       'vp.price ASC, b.name, v.name'],
    'price_desc' => ['Harga termahal',       'vp.price DESC, b.name, v.name'],
    'name_asc'   => ['Nama A–Z',             'b.name, v.name'],
    'name_desc'  => ['Nama Z–A',             'b.name DESC, v.name DESC'],
    'brand'      => ['Merk & model',         'b.name, m.name, vp.price'],
    'stock_desc' => ['Stok terbanyak',       'stock DESC, vp.price'],
    'seats_desc' => ['Kursi terbanyak',      'v.seats DESC, vp.price'],
    'cc_desc'    => ['Kapasitas mesin terbesar', 'COALESCE(v.engine_cc, 0) DESC, vp.price'],
];
$sort = input('sort');
if (!isset($sortOptions[$sort])) $sort = 'price_asc';

$st = $pdo->prepare(
    'SELECT v.id, v.name AS variant, m.name AS model, m.body_type, b.name AS brand, v.transmission, v.fuel, v.seats, vp.price,
            (SELECT path FROM variant_images i WHERE i.variant_id = v.id ORDER BY i.sort, i.id LIMIT 1) AS img,
            (SELECT COUNT(*) FROM vehicle_units u WHERE u.variant_id = v.id AND u.status = "ready") AS stock
       FROM variants v
       JOIN models m ON m.id = v.model_id JOIN brands b ON b.id = m.brand_id
       JOIN variant_prices vp ON vp.variant_id = v.id AND vp.region = :region
      WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $sortOptions[$sort][1]
);
$st->execute($p);
$cars = $st->fetchAll();
$brands = $pdo->query('SELECT id, name FROM brands ORDER BY name')->fetchAll();

$pageTitle = 'Katalog Mobil Baru';
$extraScripts = [url('assets/js/credit-calc.js')];
include BASE_PATH . '/app/views/header.php';
?>
<section class="hero text-center">
  <h1 class="display-5">Temukan Mobil <span class="text-gold">Impian</span> Anda</h1>
  <p class="text-secondary">Harga OTR <?= e($region) ?> · Simulasi kredit transparan · Test drive & servis online</p>
</section>

<div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
  <?php $chipQs = fn(int $id) => http_build_query(array_filter(['brand' => $id ?: null, 'sort' => $sort !== 'price_asc' ? $sort : null])); ?>
  <!-- <a class="brand-chip <?= $b <= 0 ? 'active' : '' ?>" href="<?= e(url('index.php') . ($chipQs(0) ? '?' . $chipQs(0) : '')) ?>">Semua merk</a> -->
  <!-- <?php foreach ($brands as $br): ?><a class="brand-chip <?= $b == $br['id'] ? 'active' : '' ?>" href="<?= e(url('index.php?' . $chipQs((int)$br['id']))) ?>"><?= e($br['name']) ?></a><?php endforeach; ?> -->
</div>

<form class="card p-3 mb-4" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4 col-lg-3"><label class="form-label small">Cari</label><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Merk / varian"></div>
    <div class="col-6 col-md-4 col-lg-2"><label class="form-label small">Merk</label>
      <select name="brand" class="form-select"><option value="">Semua</option>
      <?php foreach ($brands as $br): ?><option value="<?= (int)$br['id'] ?>" <?= $b == $br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-4 col-lg-2"><label class="form-label small">Tipe</label>
      <select name="body" class="form-select"><option value="">Semua</option>
      <?php foreach (['SUV','MPV','Sedan','Hatchback','Pickup','Coupe'] as $o): ?><option <?= $bt === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-4 col-lg-1"><label class="form-label small">Trans.</label>
      <select name="trans" class="form-select"><option value="">All</option>
      <?php foreach (['MT','AT','CVT','DCT'] as $o): ?><option <?= $tr === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-4 col-lg-2"><label class="form-label small">BBM</label>
      <select name="fuel" class="form-select"><option value="">Semua</option>
      <?php foreach (['Bensin','Diesel','Hybrid','Listrik'] as $o): ?><option <?= $fu === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-4 col-lg-2"><label class="form-label small">Harga maks</label>
      <select name="max_price" class="form-select"><option value="">Semua</option>
      <?php foreach ([200, 300, 400, 500, 750, 1000, 1500] as $o): ?><option value="<?= $o * 1000000 ?>" <?= $mp == $o * 1000000 ? 'selected' : '' ?>>≤ <?= $o ?> jt</option><?php endforeach; ?></select></div>

  </div>
  <div class="mt-3 d-flex flex-wrap align-items-center gap-2"><div class="d-flex align-items-center gap-2 me-auto"><label class="small text-secondary text-nowrap" for="sortSel"><i class="bi bi-sort-down"></i> Urutkan</label>
      <select name="sort" id="sortSel" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($sortOptions as $k => $o): ?><option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($o[0]) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-gold btn-sm"><i class="bi bi-funnel"></i> Terapkan</button><a href="<?= e(url('index.php')) ?>" class="btn btn-outline-secondary btn-sm">Reset</a></div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3 small text-secondary">
  <span>Menampilkan <strong class="text-light"><?= count($cars) ?></strong> mobil</span>
  <span>Diurutkan: <?= e($sortOptions[$sort][0]) ?></span>
</div>
<?php if (!$cars): ?>
  <div class="alert alert-secondary">Tidak ada kendaraan yang cocok dengan filter.</div>
<?php endif; ?>
<div class="row g-4">
<?php foreach ($cars as $c): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card car-card h-100">
      <?php $cover = car_cover($c['img'], $c['brand'], $c['model'], $c['variant']); ?>
      <a href="<?= e(url('vehicle.php?id=' . (int)$c['id'])) ?>">
      <?php if ($cover): ?><img class="car-img" src="<?= e($cover) ?>" alt="<?= e($c['brand'] . ' ' . $c['variant']) ?>" loading="lazy">
      <?php else: ?><div class="car-img-ph"><i class="bi bi-car-front-fill"></i></div><?php endif; ?></a>
      <div class="card-body d-flex flex-column">
        <div class="small text-secondary"><?= e($c['brand']) ?> · <?= e($c['body_type']) ?></div>
        <h5 class="card-title"><?= e($c['variant']) ?></h5>
        <div class="small text-secondary mb-2"><?= e($c['transmission']) ?> · <?= e($c['fuel']) ?> · <?= (int)$c['seats'] ?> kursi</div>
        <div class="price mb-1"><?= rupiah($c['price']) ?> <small class="text-secondary fw-normal">OTR</small></div>
        <div class="mb-3"><?= $c['stock'] > 0 ? '<span class="badge text-bg-success">Stok: ' . (int)$c['stock'] . ' unit</span>' : '<span class="badge text-bg-secondary">Indent</span>' ?></div>
        <div class="mt-auto d-grid gap-2">
          <a href="<?= e(url('vehicle.php?id=' . (int)$c['id'])) ?>" class="btn btn-outline-light btn-sm">Detail</a>
          <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#creditModal"
                  data-price="<?= e($c['price']) ?>" data-name="<?= e($c['brand'] . ' ' . $c['variant']) ?>"
                  data-quote-url="<?= e(url('quotation.php?variant=' . (int)$c['id'])) ?>"><i class="bi bi-calculator"></i> Simulasi Kredit</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php include BASE_PATH . '/app/views/credit_modal.php'; include BASE_PATH . '/app/views/footer.php';
