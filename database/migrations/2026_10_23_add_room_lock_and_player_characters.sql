-- Migration: Add room lock and player characters to MySQL database
-- Date: 2026-10-23
-- Description:
--   1. Adds character_id, accessory_id, and is_kicked to players table.
--   2. Adds is_locked to room table.
-- Eliminates the need for file-based runtime storage (storage/player-characters/ and storage/room-locks/).

USE `kahoot_btn`;

-- Add columns to players table
ALTER TABLE `players`
  ADD COLUMN IF NOT EXISTS `character_id` VARCHAR(50) NOT NULL DEFAULT '1500',
  ADD COLUMN IF NOT EXISTS `accessory_id` VARCHAR(50) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `is_kicked` TINYINT(1) NOT NULL DEFAULT 0;

-- Add column to room table
ALTER TABLE `room`
  ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0;
