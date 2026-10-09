</main>
<footer class="footer-lux py-4 mt-5">
  <div class="container small text-secondary">
    <div class="row g-3">
      <div class="col-md-5"><strong class="text-gold"><?= e(cfg('app', 'company')) ?></strong><br><?= e(cfg('app', 'address')) ?><br>Dealer resmi Honda &amp; Suzuki (demo)</div>
      <div class="col-6 col-md-3"><div class="fw-semibold mb-1">Pelanggan</div>
        <a class="link-secondary d-block" href="<?= e(url('index.php')) ?>">Katalog mobil</a>
        <a class="link-secondary d-block" href="<?= e(url('testdrive.php')) ?>">Booking test drive</a>
        <a class="link-secondary d-block" href="<?= e(url('quotation.php')) ?>">Minta penawaran</a></div>
      <div class="col-6 col-md-4"><div class="fw-semibold mb-1">Purna jual</div>
        <a class="link-secondary d-block" href="<?= e(url('service_booking.php')) ?>">Booking servis</a>
        <a class="link-secondary d-block" href="<?= e(url('booking_status.php')) ?>">Cek status booking</a>
        <a class="link-secondary d-block" href="<?= e(url('vin_lookup.php')) ?>">Riwayat servis &amp; garansi</a></div>
    </div>
    <hr class="my-3">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
      <div>&copy; <?= date('Y') ?> Sistem Informasi Penjualan &amp; Layanan Purna Jual Kendaraan Baru</div>
      <div>Foto kendaraan: <a class="link-secondary" href="https://commons.wikimedia.org" target="_blank" rel="noopener">Wikimedia Commons</a> (lisensi bebas, lihat tautan sumber di halaman detail)</div>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('themeToggle')?.addEventListener('click', () => {
  const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-bs-theme', next);
  try { localStorage.setItem('theme', next); } catch (_) {}
});
</script>
<?php foreach (($extraScripts ?? []) as $s): ?><script src="<?= e($s) ?>"></script><?php endforeach; ?>
</body>
</html>
