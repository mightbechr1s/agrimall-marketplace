-- AniMALL Migration 014: farmer contact & social details (replaces out-of-scope chat).
-- Lets buyers reach farmers directly via phone/email/social instead of in-app messaging.

ALTER TABLE `farmer_profiles`
    ADD COLUMN IF NOT EXISTS `facebook_url`  VARCHAR(255) NULL AFTER `contact_email`,
    ADD COLUMN IF NOT EXISTS `instagram_url` VARCHAR(255) NULL AFTER `facebook_url`,
    ADD COLUMN IF NOT EXISTS `tiktok_url`    VARCHAR(255) NULL AFTER `instagram_url`,
    ADD COLUMN IF NOT EXISTS `website_url`   VARCHAR(255) NULL AFTER `tiktok_url`;
