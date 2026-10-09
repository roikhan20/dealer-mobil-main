# Sistem Informasi Penjualan & Layanan Purna Jual Kendaraan Baru
**PT Penerbang Roket** — PHP 8 (native, PDO) + MySQL/MariaDB + Bootstrap 5 + Vanilla JS

## 1. Instalasi (XAMPP / Laragon / server PHP biasa)
1. Syarat: PHP ≥ 8.1 (ekstensi `pdo_mysql`, `mbstring`, `fileinfo`), MySQL 8 / MariaDB 10.5+.
2. Impor database:
   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p < database/seed.sql
   ```
3. Atur koneksi di `config/config.php` (atau env `DB_HOST DB_NAME DB_USER DB_PASS`).
4. Buat akun staf demo: `php database/seed_users.php`
5. Jalankan: arahkan *document root* ke folder `public/`, atau untuk uji lokal:
   ```bash
   php -S localhost:8000 -t public
   ```
   (Base URL terdeteksi otomatis, jadi taruh di subfolder pun tetap jalan.)
6. Pastikan `storage/` dan `public/uploads/` bisa ditulis oleh web server.

### Akun demo (password: `Password123!` — WAJIB diganti di produksi)
| Peran | Email |
|---|---|
| Admin Inventaris | inventaris@roket.test |
| Sales Executive | budi@roket.test, sari@roket.test |
| Service Advisor | service@roket.test |
| Manajer Penjualan | manager@roket.test |

### Data contoh katalog
`seed.sql` berisi **37 varian dari 20 model Honda & Suzuki** (Brio, City, Civic, Civic Type R, Accord, WR-V, BR-V, HR-V, CR-V, e:N1, S-Presso, Baleno, Fronx, Ertiga, XL7, Grand Vitara, Jimny, APV Arena, Carry) lengkap dengan warna dan unit VIN contoh. Harga OTR adalah **estimasi untuk demo**, bukan daftar harga resmi. Katalog bisa difilter (merk, tipe, transmisi, BBM, harga maks) dan **diurutkan** (harga termurah/termahal, nama A–Z/Z–A, merk & model, stok, jumlah kursi, kapasitas mesin). VIN demo riwayat servis: `MHRRV1850NJ000099`.

> Jika database lama sudah terisi, impor ulang `schema.sql` (DROP database dulu) sebelum `seed.sql`.

### Foto mobil
Setiap model Honda & Suzuki sudah punya 1–3 foto bawaan dari **Wikimedia Commons** (lisensi bebas), diatur di `app/car_photos.php`, **tanpa perubahan database**. Foto tampil langsung dari internet. Agar bisa tampil offline, jalankan sekali:
```bash
php tools/download_car_photos.php
```
Foto yang di-upload admin lewat *Master Kendaraan → Edit* selalu didahulukan daripada foto bawaan.

### Halaman per peran
| Peran | Menu |
|---|---|
| Pelanggan (tanpa login) | Katalog (filter + urutkan), Detail mobil + galeri, Simulasi kredit, Test drive, Minta penawaran, Booking servis, **Cek status booking**, Riwayat servis (VIN) |
| Admin Inventaris | Dashboard, Master kendaraan (+foto), Stok unit VIN, Parameter leasing, Profil |
| Sales Executive | Dashboard (jadwal & prospek), Leads, **Jadwal test drive** (konfirmasi/selesai/batal), SPK + cetak, Profil |
| Service Advisor | Dashboard, Slot servis, **Riwayat servis**, Catat servis walk-in, Profil |
| Manajer | Semua laporan, Leads, Test drive, SPK, Stok, Leasing, Bengkel, **Pengguna** (tambah/nonaktifkan/reset password) |

## 2. Struktur
```
config/config.php            konfigurasi (DB, wilayah harga, kuota servis, garansi)
database/schema.sql          DDL + FK + index   | seed.sql | seed_users.php
app/bootstrap.php, helpers.php   session aman, PDO, CSRF, auth/role policy, validator
app/Services/
  TdpCalculator.php          rumus kredit & TDP (server, otoritatif)
  SpkService.php             SPK + Automatic Stock Locking (FOR UPDATE)
  LeadService.php            distribusi lead otomatis
  ServiceBookingService.php  kuota bengkel anti over-booking
  Uploader.php               upload gambar aman
app/views/                   header, footer, modal simulasi kredit
public/                      halaman publik + assets (document root)
public/admin/                area staf per peran
storage/uploads/ids          foto KTP/SIM (PRIVAT, di luar akses web)
```

## 3. Peta Modul
| Modul | Halaman |
|---|---|
| 1 Katalog & Stok VIN | `index.php`, `vehicle.php`, `admin/vehicles.php`, `vehicle_form.php`, `units.php` |
| 2 Simulasi Kredit | modal di katalog (`assets/js/credit-calc.js`), `api/rates.php`, `admin/leasing.php` |
| 3 Penjualan & SPK | `testdrive.php`, `admin/spk_form.php`, `spk_view.php`, `spk_print.php` |
| 4 CRM | `quotation.php`, `admin/leads.php`, `lead_view.php`, dashboard manajer |
| 5 Purna Jual | `service_booking.php`, `vin_lookup.php`, `admin/services.php`, `service_record_form.php` |

## 4. Rumus Kredit (bunga flat)
```
DP = OTR x DP%        Pokok = OTR - DP
Bunga total = Pokok x bunga/thn x tenor/12
Angsuran = (Pokok + Bunga total) / tenor
Asuransi = OTR x rate/thn (premi tahun ke-1)
TDP = DP + Admin + Asuransi + Angsuran pertama (jika ADDM)
```
Contoh teruji: OTR 489 jt, DP 25%, 36 bln, bunga 5,9%, admin 3,5 jt, TLO 0,8% → angsuran Rp 11.990.688, TDP Rp 141.652.688.
JS di browser hanya *estimasi*; saat SPK dibuat server **menghitung ulang dari tabel leasing** dan harga OTR dari DB (input klien tidak dipercaya).

## 5. Concurrency / ACID
* **SPK**: `BEGIN → SELECT ... FROM vehicle_units WHERE id=? FOR UPDATE → cek status='ready' → UPDATE 'booked' → INSERT spk → COMMIT`. Dua sales yang memilih VIN sama: yang kedua menunggu lock lalu ditolak.
* **Kuota servis**: baris `service_capacity` per tanggal dikunci `FOR UPDATE`; ada retry otomatis saat deadlock.
* Diuji dengan proses PHP paralel: 2 proses → 1 SPK; kuota 2 dari 8 request → tepat 2 booking.

## 6. Keamanan
CSRF token di semua POST (kosong/salah → 419) · PDO prepared statement (`EMULATE_PREPARES=false`) · output di-escape (`e()`) · `password_hash`/`password_verify` + `session_regenerate_id` + pembatasan percobaan login · RBAC via `require_role()` + policy kepemilikan (sales hanya lead/SPK miliknya) · upload: cek MIME asli (finfo) + `getimagesize`, nama acak, batas ukuran, folder KTP di luar web root & disajikan lewat `admin/file.php` ber-otorisasi · folder upload publik memblokir eksekusi PHP · cookie sesi HttpOnly + SameSite · QR verifikasi hanya menampilkan data minimum (nama disamarkan).

## 7. Catatan & Batasan (jujur)
* **PDF**: dokumen SPK/Invoice dibuat sebagai halaman A4 siap cetak dengan QR Code (qrcode.js), lalu *Cetak → Simpan sebagai PDF* dari browser. DomPDF/mPDF tidak dipakai karena proyek ini tanpa Composer. Untuk PDF sisi-server: `composer require dompdf/dompdf endroid/qr-code` lalu render template `spk_print.php`.
* Library Bootstrap & QR dimuat dari CDN (butuh internet saat dipakai; bisa diunduh lokal).
* Fitur yang belum ada: notifikasi WhatsApp/email otomatis, rate limiter per-IP (hanya per-sesi), pembayaran online, audit-log lengkap.
* Produksi: aktifkan HTTPS, set `debug=false`, ganti password demo, buat user DB khusus (bukan root), backup rutin.
