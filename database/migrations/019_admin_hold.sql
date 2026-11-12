-- AgriMALL Migration 019: administrative hold on orders (FR-STAT-011..013, FR-ADM-014).
-- TiDB does not accept multiple ADD COLUMN with AFTER in one ALTER.
ALTER TABLE `orders`
    ADD COLUMN `admin_hold` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `orders`
    ADD COLUMN `admin_hold_reason` VARCHAR(500) NULL;
