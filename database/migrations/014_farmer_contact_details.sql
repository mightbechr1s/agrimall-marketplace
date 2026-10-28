-- AniMALL Migration 014: farmer contact & social details (replaces out-of-scope chat).
-- Lets buyers reach farmers directly via phone/email/social instead of in-app messaging.

ALTER TABLE `farmer_profiles`
    ADD COLUMN `facebook_url`  VARCHAR(255) NULL AFTER `contact_email`,
    ADD COLUMN `instagram_url` VARCHAR(255) NULL AFTER `facebook_url`,
    ADD COLUMN `tiktok_url`    VARCHAR(255) NULL AFTER `instagram_url`,
    ADD COLUMN `website_url`   VARCHAR(255) NULL AFTER `tiktok_url`;
