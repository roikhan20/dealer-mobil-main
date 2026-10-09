<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('sales', 'manager'); $area = 'staff'; $pdo = db();
$isMgr = $user['role'] === 'manager';
$id = input_int('id');

$st = $pdo->prepare("SELECT l.*, u.name AS sales_name, CONCAT(b.name,' ',v.name) AS car FROM leads l LEFT JOIN users u ON u.id=l.sales_id
                     LEFT JOIN variants v ON v.id=l.variant_id LEFT JOIN models m ON m.id=v.model_id LEFT JOIN brands b ON b.id=m.brand_id WHERE l.id = ?");
$st->execute([$id]); $lead = $st->fetch();
if (!$lead) { http_response_code(404); exit('Lead tidak ditemukan.'); }
// Policy: sales hanya boleh mengakses lead miliknya
if (!$isMgr && (int)$lead['sales_id'] !== (int)$user['id']) { http_response_code(403); exit('403 - Lead ini bukan milik Anda.'); }

if (is_post()) {
    $act = input('action');
    if ($act === 'log') {
        $ch = input('channel'); $note = input('note');
        if (!in_array($ch, ['telepon','whatsapp','kunjungan'], true) || mb_strlen($note) < 3) flash('danger', 'Isi kanal dan catatan interaksi.');
        else {
            $pdo->prepare('INSERT INTO lead_logs(lead_id,user_id,channel,note) VALUES (?,?,?,?)')->execute([$id, $user['id'], $ch, $note]);
            $ns = input('status');
            if (in_array($ns, ['cold','warm','hot','lost'], true) && $lead['status'] !== 'won') $pdo->prepare('UPDATE leads SET status = ? WHERE id = ?')->execute([$ns, $id]);
            flash('success', 'Interaksi dicatat.');
        }
    } elseif ($act === 'reassign' && $isMgr) {
        $to = input_int('sales_id');
        $ok = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id=? AND role='sales' AND is_active=1"); $ok->execute([$to]);
        if ($ok->fetchColumn()) {
            $pdo->prepare('UPDATE leads SET sales_id = ? WHERE id = ?')->execute([$to, $id]);
            $pdo->prepare("INSERT INTO lead_logs(lead_id,user_id,channel,note) VALUES (?,?, 'sistem', 'Lead dialihkan oleh manajer.')")->execute([$id, $user['id']]);
            flash('success', 'Lead dialihkan.');
        }
    }
    redirect('admin/lead_view.php?id=' . $id);
}
$logs = $pdo->prepare('SELECT g.*, u.name FROM lead_logs g LEFT JOIN users u ON u.id = g.user_id WHERE g.lead_id = ? ORDER BY g.id DESC'); $logs->execute([$id]); $logs = $logs->fetchAll();
$td = $pdo->prepare('SELECT * FROM test_drives WHERE lead_id = ?'); $td->execute([$id]); $td = $td->fetchAll();
$salesList = $isMgr ? $pdo->query("SELECT id, name FROM users WHERE role='sales' AND is_active=1 ORDER BY name")->fetchAll() : [];
$icons = ['telepon' => 'telephone', 'whatsapp' => 'whatsapp', 'kunjungan' => 'geo-alt', 'sistem' => 'robot'];

$pageTitle = 'Lead: ' . $lead['name'];
include BASE_PATH . '/app/views/header.php';
$wa = preg_replace('/^0/', '62', preg_replace('/\D/', '', $lead['phone']));
?>
<a href="<?= e(url('admin/leads.php')) ?>" class="small text-decoration-none text-secondary">&larr; Daftar leads</a>
<div class="row g-4 mt-1">
  <div class="col-lg-5">
    <div class="card p-4">
      <div class="d-flex justify-content-between"><h5 class="mb-0"><?= e($lead['name']) ?></h5><?= badge($lead['status']) ?></div>
      <div class="small text-secondary mb-3">Masuk <?= e(date('d M Y H:i', strtotime($lead['created_at']))) ?> · <?= $lead['source'] === 'test_drive' ? 'Test Drive' : 'Penawaran' ?></div>
      <table class="table table-sm mb-3">
        <tr><td>Telepon</td><td><?= e($lead['phone']) ?> <a class="ms-2" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>"><i class="bi bi-whatsapp text-success"></i></a></td></tr>
        <tr><td>Email</td><td><?= e($lead['email'] ?? '-') ?></td></tr>
        <tr><td>Minat</td><td><?= e($lead['car'] ?? '-') ?></td></tr>
        <tr><td>Sales</td><td><?= e($lead['sales_name'] ?? '-') ?></td></tr>
        <?php if ($lead['note']): ?><tr><td>Catatan</td><td><?= e($lead['note']) ?></td></tr><?php endif; ?>
      </table>
      <?php $tdFlow = ['requested' => ['confirmed' => 'Konfirmasi', 'cancelled' => 'Batalkan'], 'confirmed' => ['done' => 'Selesai', 'cancelled' => 'Batalkan']];
      foreach ($td as $t): ?><div class="alert alert-info py-2 small">Test drive: <?= e(date('d M Y', strtotime($t['scheduled_date']))) ?> <?= e(substr($t['scheduled_time'], 0, 5)) ?> · <?= badge($t['status']) ?> ·
        <a href="<?= e(url('admin/file.php?f=' . urlencode($t['id_photo']))) ?>" target="_blank">Lihat KTP/SIM</a>
        <?php if (!empty($tdFlow[$t['status']])): ?><div class="mt-2 d-flex gap-1">
          <?php foreach ($tdFlow[$t['status']] as $to => $lbl): ?>
            <form method="post" action="<?= e(url('admin/testdrives.php')) ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="status" value="<?= $to ?>"><input type="hidden" name="back" value="admin/lead_view.php?id=<?= $id ?>">
              <button class="btn btn-sm <?= $to === 'cancelled' ? 'btn-outline-danger' : 'btn-dark' ?>"><?= $lbl ?></button></form>
          <?php endforeach; ?></div><?php endif; ?></div><?php endforeach; ?>
      <?php if ($lead['status'] !== 'won' && $lead['status'] !== 'lost'): ?>
        <a class="btn btn-gold" href="<?= e(url('admin/spk_form.php?lead_id=' . $id)) ?>"><i class="bi bi-file-earmark-text"></i> Buat SPK</a><?php endif; ?>
      <?php if ($isMgr): ?>
        <form method="post" class="mt-3 d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="reassign">
          <select name="sales_id" class="form-select form-select-sm"><?php foreach ($salesList as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $lead['sales_id'] == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
          <button class="btn btn-sm btn-outline-light">Alihkan</button></form><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-7">
    <?php if ($lead['status'] !== 'won'): ?>
    <form method="post" class="card p-3 mb-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="log">
      <h6>Catat Interaksi Follow-up</h6>
      <div class="row g-2 mb-2">
        <div class="col-md-4"><select name="channel" class="form-select"><option value="telepon">Telepon</option><option value="whatsapp">WhatsApp</option><option value="kunjungan">Kunjungan</option></select></div>
        <div class="col-md-4"><select name="status" class="form-select"><?php foreach (['cold' => 'Cold','warm' => 'Warm','hot' => 'Hot','lost' => 'Lost'] as $k => $l2): ?><option value="<?= $k ?>" <?= $lead['status'] === $k ? 'selected' : '' ?>><?= $l2 ?></option><?php endforeach; ?></select></div></div>
      <textarea name="note" rows="2" class="form-control mb-2" placeholder="Hasil percakapan, kebutuhan, jadwal lanjutan…" required maxlength="1000"></textarea>
      <div><button class="btn btn-gold btn-sm">Simpan</button></div></form><?php endif; ?>
    <div class="card p-3"><h6>Riwayat Interaksi</h6>
      <?php foreach ($logs as $g): ?><div class="d-flex gap-3 py-2 border-top"><i class="bi bi-<?= e($icons[$g['channel']] ?? 'chat') ?> text-gold fs-5"></i>
        <div><div class="small text-secondary"><?= e(date('d M Y H:i', strtotime($g['created_at']))) ?> · <?= e($g['name'] ?? 'Sistem') ?> · <?= e($g['channel']) ?></div><div><?= nl2br(e($g['note'])) ?></div></div></div><?php endforeach; ?>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/app/views/footer.php';
