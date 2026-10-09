<?php
/**
 * Dokumen cetak SPK / Invoice Booking Fee (A4) + QR Code verifikasi.
 * Cetak lewat browser: "Cetak / Simpan PDF" -> pilih "Save as PDF".
 */
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('sales', 'manager'); $pdo = db();
$id = input_int('id'); $doc = input('doc') === 'invoice' ? 'invoice' : 'spk';
$st = $pdo->prepare("SELECT s.*, u.vin, u.engine_no, CONCAT(b.name,' ',v.name) AS car, c.name AS color, sl.name AS sales_name, lp.name AS partner
    FROM spk s JOIN vehicle_units u ON u.id=s.unit_id JOIN variants v ON v.id=u.variant_id JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id
    LEFT JOIN variant_colors c ON c.id=u.color_id JOIN users sl ON sl.id=s.sales_id LEFT JOIN leasing_partners lp ON lp.id=s.leasing_partner_id WHERE s.id = ?");
$st->execute([$id]); $s = $st->fetch();
if (!$s) { http_response_code(404); exit('Dokumen tidak ditemukan.'); }
if ($user['role'] === 'sales' && (int)$s['sales_id'] !== (int)$user['id']) { http_response_code(403); exit('403'); }

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$verifyUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('verify.php?t=' . $s['verify_token']);
$invNo = sprintf('INV-BF/%s/%05d', date('Ym', strtotime($s['created_at'])), $s['id']);
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title><?= $doc === 'spk' ? e($s['spk_no']) : e($invNo) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#e9e9e9; color:#111; font-size:13px; }
  .sheet { width:210mm; min-height:297mm; margin:12px auto; padding:16mm; background:#fff; box-shadow:0 0 8px rgba(0,0,0,.2); position:relative; }
  .kop { border-bottom:3px double #111; padding-bottom:8px; margin-bottom:14px; }
  .sign { height:70px; }
  .watermark { position:absolute; top:45%; left:12%; font-size:70px; color:rgba(200,0,0,.12); transform:rotate(-25deg); font-weight:800; pointer-events:none; }
  @media print { body { background:#fff; } .sheet { box-shadow:none; margin:0; } .no-print { display:none !important; } @page { size:A4; margin:0; } }
</style></head><body>
<div class="text-center my-3 no-print"><button class="btn btn-dark" onclick="window.print()">Cetak / Simpan PDF</button></div>
<div class="sheet">
  <?php if ($s['status'] === 'cancelled'): ?><div class="watermark">DIBATALKAN</div><?php endif; ?>
  <div class="kop d-flex justify-content-between">
    <div><h5 class="mb-0 fw-bold"><?= e(strtoupper(cfg('app', 'company'))) ?></h5><div><?= e(cfg('app', 'address')) ?></div></div>
    <div class="text-end"><h5 class="mb-0"><?= $doc === 'spk' ? 'SURAT PEMESANAN KENDARAAN' : 'INVOICE BOOKING FEE' ?></h5>
      <div>No: <strong><?= e($doc === 'spk' ? $s['spk_no'] : $invNo) ?></strong></div><div>Tanggal: <?= e(date('d M Y', strtotime($s['created_at']))) ?></div></div>
  </div>

  <h6 class="fw-bold">Data Pemesan</h6>
  <table class="table table-sm table-borderless mb-3"><tr><td width="28%">Nama</td><td>: <?= e($s['customer_name']) ?></td></tr><tr><td>NIK</td><td>: <?= e($s['nik']) ?></td></tr>
    <tr><td>No. HP</td><td>: <?= e($s['phone']) ?></td></tr><tr><td>Alamat</td><td>: <?= e($s['address']) ?></td></tr></table>

  <?php if ($doc === 'spk'): ?>
  <h6 class="fw-bold">Data Kendaraan</h6>
  <table class="table table-sm table-borderless mb-3"><tr><td width="28%">Tipe</td><td>: <?= e($s['car']) ?></td></tr><tr><td>Warna</td><td>: <?= e($s['color'] ?? '-') ?></td></tr>
    <tr><td>No. Rangka (VIN)</td><td>: <?= e($s['vin']) ?></td></tr><tr><td>No. Mesin</td><td>: <?= e($s['engine_no']) ?></td></tr>
    <tr><td>Harga OTR</td><td>: <strong><?= rupiah($s['otr_price']) ?></strong></td></tr></table>

  <h6 class="fw-bold">Pembayaran: <?= e(strtoupper($s['payment_method'])) ?></h6>
  <table class="table table-sm table-bordered mb-3">
    <?php if ($s['payment_method'] === 'kredit'): ?>
      <tr><td>Mitra Leasing</td><td><?= e($s['partner'] ?? '-') ?> — tenor <?= (int)$s['tenor'] ?> bulan, bunga flat <?= e($s['interest_rate']) ?>%/thn</td></tr>
      <tr><td>DP Murni</td><td class="text-end"><?= rupiah($s['dp_amount']) ?></td></tr>
      <tr><td>Biaya Administrasi</td><td class="text-end"><?= rupiah($s['admin_fee']) ?></td></tr>
      <tr><td>Asuransi <?= $s['insurance_type'] === 'AR' ? 'All Risk' : 'TLO' ?></td><td class="text-end"><?= rupiah($s['insurance_amount']) ?></td></tr>
      <tr><td>Angsuran Pertama (<?= $s['addm'] ? 'ADDM' : 'ADDB' ?>)</td><td class="text-end"><?= rupiah($s['addm'] ? $s['installment'] : 0) ?></td></tr>
      <tr class="fw-bold"><td>TOTAL DOWN PAYMENT (TDP)</td><td class="text-end"><?= rupiah($s['tdp']) ?></td></tr>
      <tr><td>Angsuran per bulan</td><td class="text-end"><?= rupiah($s['installment']) ?></td></tr>
    <?php else: ?><tr><td>Pelunasan tunai</td><td class="text-end"><?= rupiah($s['otr_price']) ?></td></tr><?php endif; ?>
    <tr><td>Booking Fee (sudah dibayar)</td><td class="text-end"><?= rupiah($s['booking_fee']) ?></td></tr>
  </table>
  <p class="small">Ketentuan: (1) Booking fee tidak dapat dikembalikan bila pembatalan dilakukan sepihak oleh pemesan. (2) Harga dan angsuran dapat berubah mengikuti persetujuan leasing dan kebijakan pemerintah. (3) Unit dialokasikan sesuai nomor rangka yang tertera.</p>
  <?php else: ?>
  <table class="table table-sm table-bordered mb-3"><thead class="table-light"><tr><th>Deskripsi</th><th class="text-end">Jumlah</th></tr></thead><tbody>
    <tr><td>Booking Fee <?= e($s['car']) ?> (VIN <?= e($s['vin']) ?>) — Ref. <?= e($s['spk_no']) ?></td><td class="text-end"><?= rupiah($s['booking_fee']) ?></td></tr>
    <tr class="fw-bold"><td class="text-end">TOTAL</td><td class="text-end"><?= rupiah($s['booking_fee']) ?></td></tr></tbody></table>
  <p class="small">Booking fee akan diperhitungkan sebagai pengurang pembayaran saat pelunasan / pembayaran TDP.</p>
  <?php endif; ?>

  <div class="row mt-4 text-center">
    <div class="col-4">Pemesan<div class="sign"></div><u><?= e($s['customer_name']) ?></u></div>
    <div class="col-4">Sales Executive<div class="sign"></div><u><?= e($s['sales_name']) ?></u></div>
    <div class="col-4"><div id="qr" class="d-inline-block"></div><div class="small mt-1">Scan untuk verifikasi keaslian</div></div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('qr'), { text: <?= json_encode($verifyUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>, width: 110, height: 110 });</script>
</body></html>
