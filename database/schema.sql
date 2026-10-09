-- =====================================================================
-- Sistem Informasi Penjualan & Purna Jual Kendaraan Baru
-- PT Penerbang Roket  |  MySQL 8 / MariaDB 10.5+  |  InnoDB (ACID)
-- =====================================================================
CREATE DATABASE IF NOT EXISTS dealer_roket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dealer_roket;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Auth ----------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin_inventaris','sales','service_advisor','manager') NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_role_active (role, is_active)
) ENGINE=InnoDB;

-- ---------- Modul 1: Katalog & Inventaris ----------
CREATE TABLE brands (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE models (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  body_type ENUM('SUV','MPV','Sedan','Hatchback','Pickup','Coupe') NOT NULL DEFAULT 'SUV',
  UNIQUE KEY uq_model (brand_id, name),
  CONSTRAINT fk_models_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  model_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  transmission ENUM('MT','AT','CVT','DCT') NOT NULL DEFAULT 'AT',
  fuel ENUM('Bensin','Diesel','Hybrid','Listrik') NOT NULL DEFAULT 'Bensin',
  engine_cc SMALLINT UNSIGNED NULL,
  seats TINYINT UNSIGNED NOT NULL DEFAULT 5,
  specs TEXT NULL,                       -- satu baris: "Label: Nilai"
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_variant (model_id, name),
  INDEX idx_variants_active (is_active),
  CONSTRAINT fk_variants_model FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE variant_prices (            -- Harga OTR per wilayah
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  region VARCHAR(80) NOT NULL,
  price DECIMAL(15,2) NOT NULL,
  UNIQUE KEY uq_price (variant_id, region),
  INDEX idx_price (region, price),
  CONSTRAINT fk_prices_variant FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE variant_colors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  hex CHAR(7) NOT NULL DEFAULT '#000000',
  CONSTRAINT fk_colors_variant FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE variant_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  sort TINYINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_images_variant FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vehicle_units (             -- unit fisik per VIN
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  color_id INT UNSIGNED NULL,
  vin CHAR(17) NOT NULL,
  engine_no VARCHAR(30) NOT NULL,
  status ENUM('ready','booked','delivered') NOT NULL DEFAULT 'ready',
  received_at DATE NOT NULL,
  delivered_at DATE NULL,
  UNIQUE KEY uq_vin (vin),
  UNIQUE KEY uq_engine (engine_no),
  INDEX idx_units_status_variant (status, variant_id),
  CONSTRAINT fk_units_variant FOREIGN KEY (variant_id) REFERENCES variants(id),
  CONSTRAINT fk_units_color   FOREIGN KEY (color_id)   REFERENCES variant_colors(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Modul 2: Leasing ----------
CREATE TABLE leasing_partners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  min_dp_percent DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  admin_fee DECIMAL(15,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE leasing_rates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id INT UNSIGNED NOT NULL,
  tenor TINYINT UNSIGNED NOT NULL,                    -- 12,24,36,48,60
  interest_rate DECIMAL(5,2) NOT NULL,                -- % flat per tahun
  insurance_tlo_rate DECIMAL(5,2) NOT NULL DEFAULT 0, -- % OTR per tahun
  insurance_ar_rate  DECIMAL(5,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_rate (partner_id, tenor),
  CONSTRAINT fk_rates_partner FOREIGN KEY (partner_id) REFERENCES leasing_partners(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Modul 4: CRM ----------
CREATE TABLE leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(150) NULL,
  source ENUM('test_drive','quotation') NOT NULL,
  variant_id INT UNSIGNED NULL,
  sales_id INT UNSIGNED NULL,
  status ENUM('cold','warm','hot','won','lost') NOT NULL DEFAULT 'cold',
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_leads_sales_status (sales_id, status),
  INDEX idx_leads_status (status),
  INDEX idx_leads_created (created_at),
  CONSTRAINT fk_leads_variant FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE SET NULL,
  CONSTRAINT fk_leads_sales   FOREIGN KEY (sales_id)   REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE lead_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  channel ENUM('telepon','whatsapp','kunjungan','sistem') NOT NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_logs_lead (lead_id, created_at),
  CONSTRAINT fk_logs_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Modul 3: Test Drive & SPK ----------
CREATE TABLE test_drives (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  scheduled_date DATE NOT NULL,
  scheduled_time TIME NOT NULL,
  id_photo VARCHAR(255) NOT NULL,       -- file privat di storage/uploads/ids
  status ENUM('requested','confirmed','done','cancelled') NOT NULL DEFAULT 'requested',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_td_schedule (scheduled_date, scheduled_time),
  CONSTRAINT fk_td_lead    FOREIGN KEY (lead_id)    REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_td_variant FOREIGN KEY (variant_id) REFERENCES variants(id)
) ENGINE=InnoDB;

CREATE TABLE spk (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  spk_no VARCHAR(40) NOT NULL UNIQUE,
  lead_id INT UNSIGNED NULL,
  sales_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  customer_name VARCHAR(100) NOT NULL,
  nik CHAR(16) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  address VARCHAR(255) NOT NULL,
  payment_method ENUM('cash','kredit') NOT NULL,
  leasing_partner_id INT UNSIGNED NULL,
  tenor TINYINT UNSIGNED NULL,
  otr_price DECIMAL(15,2) NOT NULL,
  dp_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  admin_fee DECIMAL(15,2) NOT NULL DEFAULT 0,
  insurance_type ENUM('TLO','AR') NULL,
  insurance_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  interest_rate DECIMAL(5,2) NULL,
  addm TINYINT(1) NOT NULL DEFAULT 1,
  installment DECIMAL(15,2) NOT NULL DEFAULT 0,
  tdp DECIMAL(15,2) NOT NULL DEFAULT 0,
  booking_fee DECIMAL(15,2) NOT NULL,
  verify_token CHAR(32) NOT NULL UNIQUE,
  status ENUM('active','delivered','cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_spk_sales_status (sales_id, status),
  INDEX idx_spk_unit (unit_id),
  INDEX idx_spk_created (created_at),
  CONSTRAINT fk_spk_lead    FOREIGN KEY (lead_id)  REFERENCES leads(id) ON DELETE SET NULL,
  CONSTRAINT fk_spk_sales   FOREIGN KEY (sales_id) REFERENCES users(id),
  CONSTRAINT fk_spk_unit    FOREIGN KEY (unit_id)  REFERENCES vehicle_units(id),
  CONSTRAINT fk_spk_partner FOREIGN KEY (leasing_partner_id) REFERENCES leasing_partners(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Modul 5: Purna Jual ----------
CREATE TABLE service_capacity (          -- kuota mekanik per hari (barisnya juga dipakai sebagai lock)
  service_date DATE PRIMARY KEY,
  capacity TINYINT UNSIGNED NOT NULL
) ENGINE=InnoDB;

CREATE TABLE service_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(20) NOT NULL UNIQUE,
  vin CHAR(17) NOT NULL,
  customer_name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  service_date DATE NOT NULL,
  service_type VARCHAR(60) NOT NULL,
  complaint TEXT NULL,
  status ENUM('booked','in_progress','done','cancelled') NOT NULL DEFAULT 'booked',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sb_date_status (service_date, status),
  INDEX idx_sb_vin (vin),
  CONSTRAINT fk_sb_unit FOREIGN KEY (vin) REFERENCES vehicle_units(vin)
) ENGINE=InnoDB;

CREATE TABLE service_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vin CHAR(17) NOT NULL,
  booking_id INT UNSIGNED NULL,
  advisor_id INT UNSIGNED NULL,
  service_date DATE NOT NULL,
  odometer INT UNSIGNED NOT NULL,
  complaint TEXT NULL,
  work_done TEXT NOT NULL,
  warranty_claim TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_sr_vin_date (vin, service_date),
  CONSTRAINT fk_sr_unit    FOREIGN KEY (vin) REFERENCES vehicle_units(vin),
  CONSTRAINT fk_sr_booking FOREIGN KEY (booking_id) REFERENCES service_bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_sr_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE service_parts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_id INT UNSIGNED NOT NULL,
  part_name VARCHAR(120) NOT NULL,
  qty SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT fk_sp_record FOREIGN KEY (record_id) REFERENCES service_records(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
