-- AniMALL Migration 016: deals / discounts on listings.
ALTER TABLE `produce_listings`
    ADD COLUMN `discount_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `price_per_unit`;
