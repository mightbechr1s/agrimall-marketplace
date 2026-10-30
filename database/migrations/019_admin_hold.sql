-- AgriMALL Migration 019: administrative hold on orders (FR-STAT-011..013, FR-ADM-014).
ALTER TABLE `orders`
    ADD COLUMN `admin_hold` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN `admin_hold_reason` VARCHAR(500) NULL AFTER `admin_hold`;
