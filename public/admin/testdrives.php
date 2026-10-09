<?php
/** Jadwal test drive: konfirmasi, tandai selesai, atau batalkan. Sales hanya melihat milik lead-nya. */
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('sales', 'manager'); $area = 'staff'; $pdo = db();
$isMgr = $user['role'] === 'manager';

/** Status berikut yang boleh dari status sekarang. */
$flow = ['requested' => ['confirmed', 'cancelled'], 'confirmed' => ['done', 'cancelled'], 'done' => [], 'cancelled' => []];

if (is_post()) {
    $id = input_int('id'); $to = input('status');
    $st = $pdo->prepare('SELECT t.id, t.status, t.lead_id, l.sales_id FROM test_drives t JOIN leads l ON l.id = t.lead_id WHERE t.id = ?');
    $st->execute([$id]); $td = $st->fetch();
    if (!$td) flash('danger', 'Jadwal test drive tidak ditemukan.');
    elseif (!$isMgr && (int)$td['sales_id'] !== (int)$user['id']) { http_response_code(403); exit('403 - Test drive ini bukan milik lead Anda.'); }
    elseif (!in_array($to, $flow[$td['status']] ?? [], true)) flash('warning', 'Perubahan status tidak diizinkan.');
    else {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE test_drives SET status = ? WHERE id = ?')->execute([$to, $id]);
        $label = ['confirmed' => 'dikonfirmasi', 'done' => 'selesai dilaksanakan', 'cancelled' => 'dibatalkan'][$to];
        $pdo->prepare("INSERT INTO lead_logs(lead_id,user_id,channel,note) VALUES (?,?,'sistem',?)")
            ->execute([$td['lead_id'], $user['id'], 'Jadwal test drive ' . $label . '.']);
        // Setelah test drive selesai, prospek biasanya "hangat": naikkan cold -> warm.
        if ($to === 'done') $pdo->prepare("UPDATE leads SET status = 'warm' WHERE id = ? AND status = 'cold'")->execute([$td['lead_id']]);
        $pdo->commit();
        flash('success', 'Test drive ' . $label . '.');
    }
    $back = input('back');
    redirect(preg_match('#^admin/lead_view\.php\?id=\d+$#', $back) ? $back : 'admin/testdrives.php?' . http_build_query(['status' => input('f_status'), 'when' => input('f_when')]));
}

$fs = input('status'); $when = input('when') ?: 'upcoming';
$where = ['1=1']; $p = [];
if (!$isMgr) { $where[] = 'l.sales_id = ?'; $p[] = $user['id']; }
if (isset($flow[$fs])) { $where[] = 't.status = ?'; $p[] = $fs; }
if ($when === 'upcoming') $where[] = 't.scheduled_date >= CURDATE()';
elseif ($when === 'past') $where[] = 't.scheduled_date < CURDATE()';
$st = $pdo->prepare(
    "SELECT t.*, l.name AS customer, l.phone, l.status AS lead_status, u.name AS sales_name,
            b.name AS brand, m.name AS model, v.name AS variant,
            (SELECT path FROM variant_images i WHERE i.variant_id = v.id ORDER BY i.sort, i.id LIMIT 1) AS img
       FROM test_drives t JOIN leads l ON l.id = t.lead_id LEFT JOIN users u ON u.id = l.sales_id
       JOIN variants v ON v.id = t.variant_id JOIN models m ON m.id = v.model_id JOIN brands b ON b.id = m.brand_id
      WHERE " . implode(' AND ', $where) . ' ORDER BY t.scheduled_date ' . ($when === 'past' ? 'DESC' : 'ASC') . ', t.scheduled_time LIMIT 300');
$st->execute($p); $rows = $st->fetchAll();

$pageTitle = 'Jadwal Test Drive';
include BASE_PATH . '/app/views/header.php';
$btn = ['confirmed' => ['Konfirmasi', 'btn-outline-gold', 'check2'], 'done' => ['Selesai', 'btn-outline-success', 'flag'], 'cancelled' => ['Batalkan', 'btn-outline-danger', 'x-lg']];
?>
<h4 class="mb-3">Jadwal Test Drive</h4>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="when" class="form-select">
    <?php foreach (['upcoming' => 'Hari ini & mendatang', 'past' => 'Sudah lewat', 'all' => 'Semua tanggal'] as $k => $l): ?><option value="<?= $k ?>" <?= $when === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select name="status" class="form-select"><option value="">Semua status</option>
    <?php foreach (array_keys($flow) as $o): ?><option value="<?= $o ?>" <?= $fs === $o ? 'selected' : '' ?>><?= ucfirst($o) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2 d-grid"><button class="btn btn-outline-light">Filter</button></div>
</form>
<div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Jadwal</th><th>Mobil</th><th>Pelanggan</th><?php if ($isMgr): ?><th>Sales</th><?php endif; ?><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $cv = car_cover($r['img'], $r['brand'], $r['model'], $r['variant']); ?>
    <tr>
      <td class="text-nowrap"><strong><?= e(date('d M Y', strtotime($r['scheduled_date']))) ?></strong><div class="small text-secondary"><?= e(substr($r['scheduled_time'], 0, 5)) ?> WIB</div></td>
      <td><div class="d-flex align-items-center gap-2"><?php if ($cv): ?><img class="car-img car-thumb" src="<?= e($cv) ?>" alt="" loading="lazy"><?php endif; ?><span class="small"><?= e($r['brand'] . ' ' . $r['variant']) ?></span></div></td>
      <td><a href="<?= e(url('admin/lead_view.php?id=' . (int)$r['lead_id'])) ?>"><?= e($r['customer']) ?></a><div class="small text-secondary"><?= e($r['phone']) ?> · <a href="<?= e(url('admin/file.php?f=' . urlencode($r['id_photo']))) ?>" target="_blank">KTP/SIM</a></div></td>
      <?php if ($isMgr): ?><td class="small"><?= e($r['sales_name'] ?? '-') ?></td><?php endif; ?>
      <td><?= badge($r['status']) ?></td>
      <td class="text-end text-nowrap">
        <?php foreach ($flow[$r['status']] as $to): [$l, $c, $i] = $btn[$to]; ?>
          <form method="post" class="d-inline" <?= $to === 'cancelled' ? 'onsubmit="return confirm(\'Batalkan test drive ini?\')"' : '' ?>><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="status" value="<?= $to ?>">
            <input type="hidden" name="f_status" value="<?= e($fs) ?>"><input type="hidden" name="f_when" value="<?= e($when) ?>">
            <button class="btn btn-sm <?= $c ?>"><i class="bi bi-<?= $i ?>"></i> <?= $l ?></button></form>
        <?php endforeach; if (!$flow[$r['status']]): ?><span class="small text-secondary">—</span><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-secondary">Tidak ada jadwal test drive.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include BASE_PATH . '/app/views/footer.php';
