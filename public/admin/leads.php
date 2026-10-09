<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('sales', 'manager'); $area = 'staff'; $pdo = db();
$isMgr = $user['role'] === 'manager';

$fs = input('status'); $where = ['1=1']; $p = [];
if (!$isMgr) { $where[] = 'l.sales_id = ?'; $p[] = $user['id']; }                    // Policy: sales hanya melihat lead miliknya
elseif (($fsales = input_int('sales')) > 0) { $where[] = 'l.sales_id = ?'; $p[] = $fsales; }
if (in_array($fs, ['cold','warm','hot','won','lost'], true)) { $where[] = 'l.status = ?'; $p[] = $fs; }
if (in_array($src = input('source'), ['test_drive','quotation'], true)) { $where[] = 'l.source = ?'; $p[] = $src; }

$st = $pdo->prepare(
    "SELECT l.*, u.name AS sales_name, CONCAT(b.name,' ',v.name) AS car,
            (SELECT note FROM lead_logs g WHERE g.lead_id = l.id ORDER BY g.id DESC LIMIT 1) AS last_note
       FROM leads l LEFT JOIN users u ON u.id = l.sales_id LEFT JOIN variants v ON v.id = l.variant_id
       LEFT JOIN models m ON m.id = v.model_id LEFT JOIN brands b ON b.id = m.brand_id
      WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(l.status,'hot','warm','cold','won','lost'), l.created_at DESC LIMIT 300"
);
$st->execute($p); $leads = $st->fetchAll();
$salesList = $isMgr ? $pdo->query("SELECT id, name FROM users WHERE role='sales' ORDER BY name")->fetchAll() : [];

$pageTitle = 'Leads / CRM';
include BASE_PATH . '/app/views/header.php';
?>
<h4 class="mb-3">Pipeline Leads</h4>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="status" class="form-select"><option value="">Semua status</option><?php foreach (['hot','warm','cold','won','lost'] as $o): ?><option <?= $fs === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select name="source" class="form-select"><option value="">Semua sumber</option><option value="test_drive" <?= $src === 'test_drive' ? 'selected' : '' ?>>Test Drive</option><option value="quotation" <?= $src === 'quotation' ? 'selected' : '' ?>>Penawaran</option></select></div>
  <?php if ($isMgr): ?><div class="col-md-3"><select name="sales" class="form-select"><option value="">Semua sales</option><?php foreach ($salesList as $s): ?><option value="<?= (int)$s['id'] ?>" <?= ($fsales ?? 0) == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="col-md-2 d-grid"><button class="btn btn-outline-light">Filter</button></div></form>
<div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Prospek</th><th>Kontak</th><th>Minat</th><th>Sumber</th><?php if ($isMgr): ?><th>Sales</th><?php endif; ?><th>Status</th><th>Catatan terakhir</th><th></th></tr></thead><tbody>
  <?php foreach ($leads as $l): ?>
    <tr><td><strong><?= e($l['name']) ?></strong><div class="small text-secondary"><?= e(date('d M Y', strtotime($l['created_at']))) ?></div></td>
      <td class="small"><?= e($l['phone']) ?></td><td class="small"><?= e($l['car'] ?? '-') ?></td>
      <td class="small"><?= $l['source'] === 'test_drive' ? 'Test Drive' : 'Penawaran' ?></td>
      <?php if ($isMgr): ?><td class="small"><?= e($l['sales_name'] ?? '-') ?></td><?php endif; ?>
      <td><?= badge($l['status']) ?></td><td class="small text-secondary"><?= e(mb_strimwidth((string)$l['last_note'], 0, 60, '…')) ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-gold" href="<?= e(url('admin/lead_view.php?id=' . (int)$l['id'])) ?>">Buka</a></td></tr>
  <?php endforeach; if (!$leads) echo '<tr><td colspan="8" class="text-secondary">Belum ada lead.</td></tr>'; ?></tbody></table></div></div>
<?php include BASE_PATH . '/app/views/footer.php';
