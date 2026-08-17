-- qc_inspection.sql
-- Database: qc_inspection
-- Created for QC INSPECTION (STEP 1)

DROP DATABASE IF EXISTS `qc_inspections`;
CREATE DATABASE `qc_inspections` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `qc_inspections`;

-- =========================
-- Table: users
-- =========================
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','qc_inspector','supervisor') NOT NULL DEFAULT 'qc_inspector',
  `employee_id` VARCHAR(50) DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: customers
-- =========================
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code` VARCHAR(50) NOT NULL UNIQUE,
  `customer_name` VARCHAR(150) NOT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: products
-- =========================
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_code` VARCHAR(100) NOT NULL UNIQUE,
  `product_name` VARCHAR(150) NOT NULL,
  `product_type` VARCHAR(100) DEFAULT NULL,
  `model` VARCHAR(100) DEFAULT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`customer_id`),
  CONSTRAINT `fk_products_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: inspection_items (master)
-- =========================
CREATE TABLE `inspection_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `process_type` ENUM('After Welding','After Painting','Final Check') NOT NULL,
  `item_code` VARCHAR(100) NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `standard` TEXT DEFAULT NULL,
  `inspection_method` VARCHAR(255) DEFAULT NULL,
  `sequence` INT DEFAULT 0,
  `status` ENUM('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  INDEX (`process_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: defects (master)
-- =========================
CREATE TABLE `defects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `defect_code` VARCHAR(50) NOT NULL UNIQUE,
  `defect_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `process` VARCHAR(100) DEFAULT NULL,
  `severity` ENUM('low','medium','high') DEFAULT 'medium',
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: inspection_headers
-- =========================
CREATE TABLE `inspection_headers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inspection_no` VARCHAR(50) NOT NULL UNIQUE,
  `inspection_type` ENUM('After Welding','After Painting','Final Check') NOT NULL,
  `inspection_date` DATE NOT NULL,
  `inspection_time` TIME DEFAULT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `model` VARCHAR(100) DEFAULT NULL,
  `part_number` VARCHAR(100) DEFAULT NULL,
  `serial_number` VARCHAR(100) DEFAULT NULL,
  `production_order` VARCHAR(100) DEFAULT NULL,
  `lot_number` VARCHAR(100) DEFAULT NULL,
  `line` VARCHAR(100) DEFAULT NULL,
  `shift` VARCHAR(50) DEFAULT NULL,
  `inspector_id` INT UNSIGNED DEFAULT NULL,
  `final_result` ENUM('PASS','NG','HOLD') DEFAULT NULL,
  `remark` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`product_id`),
  INDEX (`inspector_id`),
  CONSTRAINT `fk_inspection_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inspection_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: inspection_details
-- =========================
CREATE TABLE `inspection_details` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inspection_header_id` INT UNSIGNED NOT NULL,
  `inspection_item_id` INT UNSIGNED NOT NULL,
  `standard` TEXT DEFAULT NULL,
  `method` VARCHAR(255) DEFAULT NULL,
  `result` ENUM('OK','NG','N/A') DEFAULT 'OK',
  `status` VARCHAR(50) DEFAULT NULL,
  `defect_id` INT UNSIGNED DEFAULT NULL,
  `defect_location` VARCHAR(255) DEFAULT NULL,
  `remark` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`inspection_header_id`),
  INDEX (`inspection_item_id`),
  INDEX (`defect_id`),
  CONSTRAINT `fk_detail_header` FOREIGN KEY (`inspection_header_id`) REFERENCES `inspection_headers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_item` FOREIGN KEY (`inspection_item_id`) REFERENCES `inspection_items`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_defect` FOREIGN KEY (`defect_id`) REFERENCES `defects`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: defect_photos
-- =========================
CREATE TABLE `defect_photos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inspection_detail_id` INT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`inspection_detail_id`),
  CONSTRAINT `fk_photo_detail` FOREIGN KEY (`inspection_detail_id`) REFERENCES `inspection_details`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: activity_logs
-- =========================
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `activity` VARCHAR(255) NOT NULL,
  `module` VARCHAR(100) DEFAULT NULL,
  `reference_id` VARCHAR(100) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`user_id`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: inspection_history
-- =========================
CREATE TABLE `inspection_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inspection_header_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`inspection_header_id`),
  CONSTRAINT `fk_history_header` FOREIGN KEY (`inspection_header_id`) REFERENCES `inspection_headers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Sample master data
-- =========================
INSERT INTO `customers` (`customer_code`, `customer_name`, `status`) VALUES
('CUST-001', 'PT. Contoh Customer', 'active');

INSERT INTO `products` (`product_code`, `product_name`, `product_type`, `model`, `customer_id`, `status`) VALUES
('PRD-FUEL-001','Fuel Tank','Tank','FT-100',1,'active'),
('PRD-HYD-001','HYD Tank','Tank','HYD-200',1,'active');

-- Sample defects
INSERT INTO `defects` (`defect_code`, `defect_name`, `category`, `process`, `severity`, `description`, `status`) VALUES
('D001','Crack','Welding','After Welding','high','Retak pada sambungan las','active'),
('D002','Porosity','Welding','After Welding','medium','Pori pada las','active'),
('D003','Undercut','Welding','After Welding','medium','Undercut pada tepian las','active'),
('D004','Spatter','Welding','After Welding','low','Percikan las menempel','active'),
('D005','Scratch','Painting','After Painting','low','Goresan pada permukaan','active'),
('D006','Dent','Painting','After Painting','medium','Penyok pada bodi','active'),
('D007','Bubble','Painting','After Painting','medium','Gelembung cat','active'),
('D008','Peeling','Painting','After Painting','high','Pengelupasan cat','active'),
('D009','Dimension NG','Final','Final Check','high','Dimensi tidak sesuai','active'),
('D010','Missing Component','Final','Final Check','high','Komponen hilang','active');

-- Sample inspection items (basic examples)
INSERT INTO `inspection_items` (`process_type`, `item_code`, `item_name`, `standard`, `inspection_method`, `sequence`, `status`) VALUES
('After Welding','AW-01','Weld Appearance','No crack, porosity','Visual',1,'active'),
('After Welding','AW-02','Weld Continuity','Continuous weld bead','Visual',2,'active'),
('After Welding','AW-03','Porosity Check','No porosity allowed','Visual',3,'active'),
('After Painting','AP-01','Paint Appearance','Uniform color and coverage','Visual',1,'active'),
('After Painting','AP-02','Thickness','Between spec range','Coating Thickness Gauge',2,'active'),
('Final Check','FC-01','Overall Appearance','No visible defects','Visual',1,'active'),
('Final Check','FC-02','Leakage Check','No leakage under pressure','Functional Test',2,'active');

-- =========================
-- Sample admin user (username: admin, password: admin123)
-- Password hashed with PHP password_hash (BCRYPT)
-- =========================
INSERT INTO `users` (`name`, `username`, `password`, `role`, `employee_id`, `department`, `status`) VALUES
('Administrator','admin','$2y$10$MSjKTCOwXAmgBpU8XmLnO.nRE79c6nmvbPr5JvU9bVaD9cVJ86aFm','admin','EMP-0001','QC','active');

-- NOTE:
-- Replace ${ADMIN_HASH_PLACEHOLDER} with the generated PHP password_hash value if importing manually.
-- If imported as-is, run the following SQL after replacing placeholder or use provided setup script.

-- End of file
