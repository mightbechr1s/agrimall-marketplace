-- AgriMALL Migration 011: add reason column to admin_audit_log.
-- Run after 010 against `animal_marketplace`; additive, extends an existing table.

USE `animal_marketplace`;

ALTER TABLE `admin_audit_log`
    ADD COLUMN `reason` TEXT NULL AFTER `entity_id`;
