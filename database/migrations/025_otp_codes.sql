-- OTP codes for email/phone verification (replaces CAPTCHA for bot detection).
-- One row per issued code; consumed on successful verification.

CREATE TABLE IF NOT EXISTS `otp_codes` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipient`    VARCHAR(255) NOT NULL,        -- email or phone number
    `channel`      ENUM('email','sms') NOT NULL DEFAULT 'email',
    `purpose`      VARCHAR(64) NOT NULL,          -- 'register', 'login', 'password_reset'
    `code_hash`    VARCHAR(255) NOT NULL,         -- bcrypt/argon of the 6-digit code
    `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `expires_at`   DATETIME NOT NULL,
    `used_at`      DATETIME NULL DEFAULT NULL,
    `ip_address`   VARCHAR(45) NULL DEFAULT NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_otp_recipient` (`recipient`, `purpose`, `created_at`),
    KEY `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
