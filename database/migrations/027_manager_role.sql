-- Add 'manager' role for business-side operations.
-- Admins handle technical side (system config, user management, audit logs).
-- Managers handle business side (orders, listings, verifications, reports).

ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('buyer','farmer','admin','manager') NOT NULL DEFAULT 'buyer';
