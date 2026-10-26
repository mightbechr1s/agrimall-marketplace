-- AniMALL Migration 010: add admin_deactivated to the listing status enum.
-- Run after 009 against `animal_marketplace`; additive, extends an existing enum.
-- This closes the gap where FR-LIST-025 required an administrator deactivation
-- path but no state existed to represent it.

USE `animal_marketplace`;

ALTER TABLE `produce_listings`
    MODIFY `status` ENUM('draft','active','paused','sold_out','archived','admin_deactivated')
        NOT NULL DEFAULT 'draft';
