<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('admin_inventaris'); $area = 'staff'; $pdo = db();
$region = cfg('app', 'region');
$id = input_int('id');
$v = ['brand' => '', 'model' => '', 'body_type' => 'SUV', 'name' => '', 'transmission' => 'AT', 'fuel' => 'Bensin', 'engine_cc' => '', 'seats' => 5, 'specs' => '', 'description' => '', 'price' => '', 'colors' => ''];
$images = [];

if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = $pdo->prepare('SELECT v.*, m.name AS model, m.body_type, b.name AS brand, vp.price FROM variants v JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id LEFT JOIN variant_prices vp ON vp.variant_id=v.id AND vp.region=? WHERE v.id=?');
    $st->execute([$region, $id]); $row = $st->fetch();
    if (!$row) { http_response_code(404); exit('Data tidak ditemukan.'); }
    $v = array_merge($v, $row);
    $c = $pdo->prepare('SELECT name, hex FROM variant_colors WHERE variant_id = ?'); $c->execute([$id]);
    $v['colors'] = implode("\n", array_map(fn($x) => $x['name'] . ':' . $x['hex'], $c->fetchAll()));
}
if ($id) { $i = $pdo->prepare('SELECT id, path FROM variant_images WHERE variant_id = ? ORDER BY sort, id'); $i->execute([$id]); $images = $i->fetchAll(); }

if (is_post()) {
    // Hapus gambar tunggal
    if (input('action') === 'del_image') {
        $s = $pdo->prepare('SELECT path FROM variant_images WHERE id = ? AND variant_id = ?'); $s->execute([input_int('image_id'), $id]);
        if ($p = $s->fetchColumn()) { @unlink(BASE_PATH . '/public/uploads/vehicles/' . basename($p)); $pdo->prepare('DELETE FROM variant_images WHERE id = ?')->execute([input_int('image_id')]); }
        redirect('admin/vehicle_form.php?id=' . $id);
    }
    $v = array_merge($v, [
        'brand' => input('brand'), 'model' => input('model'), 'body_type' => input('body_type'), 'name' => input('name'),
        'transmission' => input('transmission'), 'fuel' => input('fuel'), 'engine_cc' => input('engine_cc'), 'seats' => input_int('seats', 5),
        'price' => input('price'),
        'specs' => trim(strip_tags((string)($_POST['specs'] ?? ''))), 'description' => trim(strip_tags((string)($_POST['description'] ?? ''))),
        'colors' => trim(strip_tags((string)($_POST['colors'] ?? ''))),
    ]);
    $err = [];
    if ($v['brand'] === '' || $v['model'] === '' || $v['name'] === '') $err[] = 'Merk, model, dan nama varian wajib diisi.';
    if (!in_array($v['body_type'], ['SUV','MPV','Sedan','Hatchback','Pickup','Coupe'], true)) $err[] = 'Tipe bodi tidak valid.';
    if (!in_array($v['transmission'], ['MT','AT','CVT','DCT'], true)) $err[] = 'Transmisi tidak valid.';
    if (!in_array($v['fuel'], ['Bensin','Diesel','Hybrid','Listrik'], true)) $err[] = 'Bahan bakar tidak valid.';
    $price = (float)preg_replace('/[^\d]/', '', (string)$v['price']);
    if ($price < 50000000) $err[] = 'Harga OTR tidak valid (min Rp 50.000.000).';

    $colors = [];
    foreach (preg_split('/\R/', $v['colors'], -1, PREG_SPLIT_NO_EMPTY) as $line) {
        [$cn, $ch] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '#000000');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $ch)) { $err[] = "Hex warna salah pada '$cn' (format #RRGGBB)."; continue; }
        $colors[] = [$cn, $ch];
    }
    if (!$err) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT IGNORE INTO brands(name) VALUES (?)')->execute([$v['brand']]);
            $bid = $pdo->prepare('SELECT id FROM brands WHERE name = ?'); $bid->execute([$v['brand']]); $bid = (int)$bid->fetchColumn();
            $pdo->prepare('INSERT INTO models(brand_id,name,body_type) VALUES (?,?,?) ON DUPLICATE KEY UPDATE body_type = VALUES(body_type)')->execute([$bid, $v['model'], $v['body_type']]);
            $mid = $pdo->prepare('SELECT id FROM models WHERE brand_id = ? AND name = ?'); $mid->execute([$bid, $v['model']]); $mid = (int)$mid->fetchColumn();
            $cc = $v['engine_cc'] === '' ? null : (int)$v['engine_cc'];
            if ($id) {
                $pdo->prepare('UPDATE variants SET model_id=?, name=?, transmission=?, fuel=?, engine_cc=?, seats=?, specs=?, description=? WHERE id=?')
                    ->execute([$mid, $v['name'], $v['transmission'], $v['fuel'], $cc, $v['seats'], $v['specs'], $v['description'], $id]);
            } else {
                $pdo->prepare('INSERT INTO variants(model_id,name,transmission,fuel,engine_cc,seats,specs,description) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$mid, $v['name'], $v['transmission'], $v['fuel'], $cc, $v['seats'], $v['specs'], $v['description']]);
                $id = (int)$pdo->lastInsertId();
            }
            $pdo->prepare('INSERT INTO variant_prices(variant_id,region,price) VALUES (?,?,?) ON DUPLICATE KEY UPDATE price = VALUES(price)')->execute([$id, $region, $price]);

            // Warna: sinkronisasi per nama agar unit fisik yang sudah memakai warna tidak kehilangan relasi.
            $ex = $pdo->prepare('SELECT id, name FROM variant_colors WHERE variant_id = ?'); $ex->execute([$id]);
            $existing = array_column($ex->fetchAll(), 'id', 'name');
            $keep = [];
            foreach ($colors as [$cn, $ch]) {
                if (isset($existing[$cn])) { $pdo->prepare('UPDATE variant_colors SET hex = ? WHERE id = ?')->execute([$ch, $existing[$cn]]); $keep[] = $existing[$cn]; }
                else { $pdo->prepare('INSERT INTO variant_colors(variant_id,name,hex) VALUES (?,?,?)')->execute([$id, $cn, $ch]); $keep[] = (int)$pdo->lastInsertId(); }
            }
            foreach ($existing as $cid) if (!in_array((int)$cid, array_map('intval', $keep), true)) $pdo->prepare('DELETE FROM variant_colors WHERE id = ?')->execute([$cid]);

            // Galeri (multi upload)
            if (!empty($_FILES['images']['name'][0])) {
                $n = count($_FILES['images']['name']);
                $insImg = $pdo->prepare('INSERT INTO variant_images(variant_id,path,sort) VALUES (?,?,?)');
                for ($k = 0; $k < min($n, 8); $k++) {
                    $f = ['name' => $_FILES['images']['name'][$k], 'tmp_name' => $_FILES['images']['tmp_name'][$k], 'size' => $_FILES['images']['size'][$k], 'error' => $_FILES['images']['error'][$k]];
                    if ($f['error'] === UPLOAD_ERR_NO_FILE) continue;
                    $insImg->execute([$id, Uploader::saveImage($f, BASE_PATH . '/public/uploads/vehicles', 4_194_304), $k]);
                }
            }
            $pdo->commit();
            flash('success', 'Varian tersimpan.');
            redirect('admin/vehicle_form.php?id=' . $id);
        } catch (RuntimeException $ex) { $pdo->rollBack(); flash('danger', $ex->getMessage()); }
          catch (PDOException $ex) { $pdo->rollBack(); flash('danger', ($ex->errorInfo[1] ?? 0) == 1062 ? 'Nama varian sudah ada pada model tersebut.' : 'Gagal menyimpan data.'); }
    } else flash('danger', implode(' ', $err));
}
$pageTitle = $id ? 'Edit Varian' : 'Tambah Varian';
include BASE_PATH . '/app/views/header.php';
?>
<a href="<?= e(url('admin/vehicles.php')) ?>" class="small text-decoration-none text-secondary">&larr; Master kendaraan</a>
<h4 class="my-3"><?= e($pageTitle) ?></h4>
<form method="post" enctype="multipart/form-data" class="card p-4"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-3"><label class="form-label">Merk</label><input name="brand" class="form-control" required value="<?= e($v['brand']) ?>"></div>
    <div class="col-md-3"><label class="form-label">Model</label><input name="model" class="form-control" required value="<?= e($v['model']) ?>"></div>
    <div class="col-md-3"><label class="form-label">Tipe bodi</label><select name="body_type" class="form-select"><?php foreach (['SUV','MPV','Sedan','Hatchback','Pickup','Coupe'] as $o): ?><option <?= $v['body_type'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Nama varian</label><input name="name" class="form-control" required value="<?= e($v['name']) ?>"></div>
    <div class="col-md-2"><label class="form-label">Transmisi</label><select name="transmission" class="form-select"><?php foreach (['MT','AT','CVT','DCT'] as $o): ?><option <?= $v['transmission'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">BBM</label><select name="fuel" class="form-select"><?php foreach (['Bensin','Diesel','Hybrid','Listrik'] as $o): ?><option <?= $v['fuel'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">CC mesin</label><input type="number" name="engine_cc" class="form-control" value="<?= e($v['engine_cc']) ?>"></div>
    <div class="col-md-2"><label class="form-label">Kursi</label><input type="number" name="seats" min="2" max="9" class="form-control" value="<?= e($v['seats']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Harga OTR <?= e($region) ?> (Rp)</label><input name="price" class="form-control" required value="<?= e(($v['price'] !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') ? number_format((float)$v['price'], 0, '', '') : $v['price']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Spesifikasi teknis <small class="text-secondary">(satu baris: Label: Nilai)</small></label><textarea name="specs" rows="6" class="form-control"><?= e($v['specs']) ?></textarea></div>
    <div class="col-md-6"><label class="form-label">Pilihan warna <small class="text-secondary">(satu baris: Nama:#RRGGBB)</small></label><textarea name="colors" rows="6" class="form-control" placeholder="Pearl White:#f5f5f0"><?= e($v['colors']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Deskripsi</label><textarea name="description" rows="3" class="form-control"><?= e($v['description']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Galeri gambar <small class="text-secondary">(maks 8 file, 4 MB/file)</small></label><input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-control"></div>
  </div>
  <div class="mt-4"><button class="btn btn-gold">Simpan</button></div>
</form>
<?php if (!$images && $id && ($def = car_photos($v['brand'], $v['model'], $v['name']))): ?>
<div class="card p-3 mt-3"><h6>Foto bawaan <small class="text-secondary fw-normal">— dipakai di katalog selama belum ada foto upload (sumber: Wikimedia Commons)</small></h6><div class="row g-2">
  <?php foreach ($def as $ph): ?><div class="col-6 col-md-3"><a href="<?= e($ph['credit']) ?>" target="_blank" rel="noopener"><img class="car-img rounded" src="<?= e($ph['src']) ?>" alt="" loading="lazy"></a></div><?php endforeach; ?>
</div></div>
<?php endif; ?>
<?php if ($images): ?>
<div class="card p-3 mt-3"><h6>Galeri saat ini</h6><div class="row g-2">
  <?php foreach ($images as $im): ?><div class="col-6 col-md-3"><div class="card p-1 text-center"><img class="car-img" src="<?= e(url('uploads/vehicles/' . $im['path'])) ?>" alt="">
    <form method="post" class="mt-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="del_image"><input type="hidden" name="image_id" value="<?= (int)$im['id'] ?>"><button class="btn btn-sm btn-outline-danger w-100">Hapus</button></form></div></div><?php endforeach; ?>
</div></div><?php endif; ?>
<?php include BASE_PATH . '/app/views/footer.php';
