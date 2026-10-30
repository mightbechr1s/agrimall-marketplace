-- AgriMALL Migration 015: track the price at the time a listing was saved.
ALTER TABLE `saved_listings`
    ADD COLUMN IF NOT EXISTS `price_at_save` DECIMAL(10,2) NULL AFTER `listing_id`;
