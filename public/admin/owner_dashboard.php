<?php
/**
 * Dashboard Owner: rekap pemasukan per bulan.
 *
 * Definisi pemasukan (dihitung dari SPK yang tidak dibatalkan, dikelompokkan per bulan
 * berdasarkan tanggal SPK dibuat):
 *   - Pemasukan (TDP)  = SUM(spk.tdp)  -> uang muka yang dibayar pembeli saat SPK
 *   - Nilai penjualan  = SUM(spk.otr_price) -> nilai kontrak OTR (bukan kas masuk)
 * Pemasukan servis belum bisa dihitung karena tabel servis tidak menyimpan harga.
 */
require __DIR__ . '/../../app/bootstrap.php';

$user = require_role('owner');
$area = 'staff';
$pdo = db();
$pageTitle = 'Dashboard Owner';

$namaBulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// Tahun yang dipilih (default: tahun berjalan).
$tahunNow = (int)date('Y');
$tahun = (int)($_GET['tahun'] ?? $tahunNow);
if ($tahun < 2000 || $tahun > 2100) $tahun = $tahunNow;

// Daftar tahun yang tersedia dari data SPK + tahun berjalan.
$tahunList = array_map('intval', $pdo->query("SELECT DISTINCT YEAR(created_at) FROM spk")->fetchAll(PDO::FETCH_COLUMN));
$tahunList[] = $tahunNow;
$tahunList = array_values(array_unique($tahunList));
rsort($tahunList);

// Rekap SPK per bulan.
$st = $pdo->prepare(
    "SELECT MONTH(created_at) AS bln,
            COUNT(*) AS jml_spk,
            SUM(payment_method = 'cash')   AS cash,
            SUM(payment_method = 'kredit') AS kredit,
            COALESCE(SUM(tdp), 0)       AS pemasukan,
            COALESCE(SUM(otr_price), 0) AS nilai_otr
       FROM spk
      WHERE status <> 'cancelled' AND YEAR(created_at) = ?
      GROUP BY MONTH(created_at)"
);
$st->execute([$tahun]);
$perBulanRaw = [];
foreach ($st->fetchAll() as $r) $perBulanRaw[(int)$r['bln']] = $r;

// Jumlah pekerjaan servis per bulan (sebagai indikator aktivitas bengkel).
$st = $pdo->prepare("SELECT MONTH(service_date) AS bln, COUNT(*) AS jml FROM service_records WHERE YEAR(service_date) = ? GROUP BY MONTH(service_date)");
$st->execute([$tahun]);
$servisRaw = [];
foreach ($st->fetchAll() as $r) $servisRaw[(int)$r['bln']] = (int)$r['jml'];

// Susun 12 bulan (bulan tanpa transaksi tetap tampil sebagai 0).
$perBulan = [];
$tot = ['jml_spk' => 0, 'cash' => 0, 'kredit' => 0, 'pemasukan' => 0.0, 'nilai_otr' => 0.0, 'servis' => 0];
for ($b = 1; $b <= 12; $b++) {
    $r = $perBulanRaw[$b] ?? [];
    $row = [
        'nama'       => $namaBulan[$b - 1],
        'jml_spk'    => (int)($r['jml_spk'] ?? 0),
        'cash'       => (int)($r['cash'] ?? 0),
        'kredit'     => (int)($r['kredit'] ?? 0),
        'pemasukan'  => (float)($r['pemasukan'] ?? 0),
        'nilai_otr'  => (float)($r['nilai_otr'] ?? 0),
        'servis'     => $servisRaw[$b] ?? 0,
    ];
    foreach (['jml_spk', 'cash', 'kredit', 'pemasukan', 'nilai_otr', 'servis'] as $k) $tot[$k] += $row[$k];
    $perBulan[$b] = $row;
}

// Bulan dengan pemasukan tertinggi.
$bulanTertinggi = null;
foreach ($perBulan as $row) {
    if ($row['pemasukan'] > 0 && ($bulanTertinggi === null || $row['pemasukan'] > $bulanTertinggi['pemasukan'])) $bulanTertinggi = $row;
}

// Data untuk grafik.
$chartLabels = array_column($perBulan, 'nama');
$chartPemasukan = array_column($perBulan, 'pemasukan');

include BASE_PATH . '/app/views/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
  <div>
    <h3 class="mb-1">Dashboard Owner</h3>
    <p class="text-secondary mb-0">Rekap pemasukan per bulan · <?= e($user['name']) ?></p>
  </div>
  <form method="get" class="d-flex gap-2 align-items-center">
    <label class="small text-secondary" for="tahun">Tahun</label>
    <select name="tahun" id="tahun" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($tahunList as $t): ?>
        <option value="<?= $t ?>" <?= $t === $tahun ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card stat-card p-3">
    <div class="text-secondary small">Total pemasukan (TDP) <?= $tahun ?></div>
    <div class="fs-5 fw-bold text-gold"><?= rupiah($tot['pemasukan']) ?></div>
  </div></div>
  <div class="col-6 col-md-3"><div class="card stat-card p-3">
    <div class="text-secondary small">Jumlah SPK</div>
    <div class="display-6"><?= (int)$tot['jml_spk'] ?></div>
    <div class="small text-secondary">Cash <?= (int)$tot['cash'] ?> · Kredit <?= (int)$tot['kredit'] ?></div>
  </div></div>
  <div class="col-6 col-md-3"><div class="card stat-card p-3">
    <div class="text-secondary small">Nilai penjualan (OTR)</div>
    <div class="fs-5 fw-bold"><?= rupiah($tot['nilai_otr']) ?></div>
  </div></div>
  <div class="col-6 col-md-3"><div class="card stat-card p-3">
    <div class="text-secondary small">Bulan pemasukan tertinggi</div>
    <div class="fs-5 fw-bold"><?= $bulanTertinggi ? e($bulanTertinggi['nama']) . ' · ' . rupiah($bulanTertinggi['pemasukan']) : '—' ?></div>
  </div></div>
</div>

<div class="card p-3 mb-3">
  <h6>Grafik pemasukan per bulan</h6>
  <canvas id="chartPemasukan" height="90"></canvas>
</div>

<div class="card p-3">
  <h6>Rincian per bulan</h6>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Bulan</th>
          <th class="text-end">Jumlah SPK</th>
          <th class="text-end">Cash</th>
          <th class="text-end">Kredit</th>
          <th class="text-end">Pemasukan (TDP)</th>
          <th class="text-end">Nilai OTR</th>
          <th class="text-end">Pekerjaan servis</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($perBulan as $row): ?>
          <tr class="<?= $row['pemasukan'] == 0 && $row['jml_spk'] == 0 ? 'text-secondary' : '' ?>">
            <td><?= e($row['nama']) ?></td>
            <td class="text-end"><?= $row['jml_spk'] ?></td>
            <td class="text-end"><?= $row['cash'] ?></td>
            <td class="text-end"><?= $row['kredit'] ?></td>
            <td class="text-end"><?= rupiah($row['pemasukan']) ?></td>
            <td class="text-end"><?= rupiah($row['nilai_otr']) ?></td>
            <td class="text-end"><?= $row['servis'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="fw-bold">
          <td>Total</td>
          <td class="text-end"><?= (int)$tot['jml_spk'] ?></td>
          <td class="text-end"><?= (int)$tot['cash'] ?></td>
          <td class="text-end"><?= (int)$tot['kredit'] ?></td>
          <td class="text-end text-gold"><?= rupiah($tot['pemasukan']) ?></td>
          <td class="text-end"><?= rupiah($tot['nilai_otr']) ?></td>
          <td class="text-end"><?= (int)$tot['servis'] ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <p class="small text-secondary mt-2 mb-0">
    Pemasukan (TDP) dihitung dari SPK yang tidak dibatalkan, dikelompokkan menurut bulan pembuatan SPK.
    Nilai OTR adalah nilai kontrak, bukan kas yang sudah diterima. Pemasukan servis belum dihitung
    karena data servis belum mencatat harga; kolom servis menampilkan jumlah pekerjaan saja.
  </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartPemasukan'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
      label: 'Pemasukan (TDP)',
      data: <?= json_encode($chartPemasukan) ?>,
      backgroundColor: 'rgba(212, 175, 55, 0.75)',
      borderRadius: 4
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + Number(v).toLocaleString('id-ID') } }
    }
  }
});
</script>
<?php include BASE_PATH . '/app/views/footer.php'; ?>
