-- 031: widen the columns that now hold AES-256-GCM ciphertext.
-- Stored values are "enc:v1:" + base64(iv[12] + tag[16] + ciphertext), so a
-- 32-char TOTP seed or a <=50-char ID number needs more than 64/50 bytes.
-- Legacy plaintext rows still fit: the columns only get wider.
-- MODIFY is safe to re-run; it reapplies the same definition.
USE `animal_marketplace`;

ALTER TABLE users
  MODIFY totp_secret VARCHAR(255) NULL;

ALTER TABLE farmer_profiles
  MODIFY valid_id_number VARCHAR(255) NOT NULL;
