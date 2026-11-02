-- =====================================================================
-- Migrasi: alur pemesanan (jalankan sekali pada database yang sudah ada)
--   mysql -u root -p dealer_roket < database/migration_order_flow.sql
-- Perubahan:
--   1) Foto KTP/SIM pada booking test drive menjadi OPSIONAL (boleh NULL).
-- Catatan: tidak ada perubahan tabel lain. Ringkasan simulasi & warna pelanggan
--          disimpan pada leads.note agar struktur database tetap kompatibel.
-- =====================================================================
USE dealer_roket;
ALTER TABLE test_drives MODIFY id_photo VARCHAR(255) NULL;
