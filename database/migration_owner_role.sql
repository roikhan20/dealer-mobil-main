-- Migrasi: tambah peran 'owner' (Pemilik) untuk dashboard owner.
-- Jalankan sekali pada database yang sudah ada:
--   mysql -u root -p dealer_roket < database/migration_owner_role.sql
-- Tidak mengubah data lain.

ALTER TABLE users
  MODIFY role ENUM('admin_inventaris','sales','service_advisor','manager','owner') NOT NULL;
