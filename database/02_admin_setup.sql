-- The supplied Brilliant Directories SQL dump has admin_roles and related permission
-- tables, but it does not define the administrators table referenced by those tables.
-- This migration adds only the missing administrator login table.
CREATE TABLE IF NOT EXISTS `administrators` (
  `admin_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_administrators_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
