-- AgriMALL Migration 020: session invalidation support (FR-ADM-004).
ALTER TABLE `users`
    ADD COLUMN `session_version` INT NOT NULL DEFAULT 0 AFTER `status`;