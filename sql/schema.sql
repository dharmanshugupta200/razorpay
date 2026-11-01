-- Run this in phpMyAdmin > demo_pagination > SQL tab.
-- It keeps your existing `users` and `payment` tables and extends them.
-- (If you prefer a clean start, uncomment the two DROP lines.)

-- DROP TABLE IF EXISTS payment;
-- DROP TABLE IF EXISTS users;

CREATE TABLE IF NOT EXISTS users (
  id          INT(11) NOT NULL AUTO_INCREMENT,
  name        VARCHAR(255) NOT NULL,
  username    VARCHAR(255) NOT NULL,
  password    LONGTEXT NOT NULL,                -- stores password_hash(), never plain text
  timecreated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment (
  id          INT(11) NOT NULL AUTO_INCREMENT,
  response    LONGTEXT NOT NULL,                -- raw JSON from Razorpay / checkout for this attempt
  status      VARCHAR(50) DEFAULT NULL,         -- created | success | failed | cancelled | expired | duplicate
  timecreated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Upgrade the tables you already have (safe to run once) ----
-- Needed so emoji/₹/non-latin text in names and gateway responses is not mangled.
ALTER TABLE users   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE payment CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- A username must be unique, otherwise two people can register the same login.
ALTER TABLE users ADD UNIQUE KEY uq_users_username (username);

-- Link every payment attempt to a user + Razorpay IDs.
ALTER TABLE payment
  ADD COLUMN user_id             INT(11)      NOT NULL AFTER id,
  ADD COLUMN razorpay_order_id   VARCHAR(100) DEFAULT NULL AFTER user_id,
  ADD COLUMN razorpay_payment_id VARCHAR(100) DEFAULT NULL AFTER razorpay_order_id,
  ADD COLUMN amount              INT(11)      NOT NULL DEFAULT 0 AFTER razorpay_payment_id,   -- in paise
  ADD COLUMN currency            VARCHAR(10)  NOT NULL DEFAULT 'INR' AFTER amount,
  ADD COLUMN error_code          VARCHAR(100) DEFAULT NULL AFTER status,
  ADD COLUMN error_description   VARCHAR(500) DEFAULT NULL AFTER error_code,
  ADD COLUMN timeupdated         TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  ADD UNIQUE KEY uq_payment_order (razorpay_order_id),
  ADD KEY idx_payment_user_status (user_id, status),
  ADD CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
