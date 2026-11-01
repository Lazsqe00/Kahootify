-- Migration: Add Obstacle Puzzle mode columns
-- Date: 2026-11-01
-- Description:
--   1. Adds game_type, obstacle_image, obstacle_answer, total_pieces to quiz table.
--   2. Adds piece_index to questions table.
--   3. Adds game_type, opened_pieces, buzzer_player_id, buzzer_status, buzzer_time, winner_player_id to room table.
--   4. Adds unlocked_pieces, is_disqualified_obstacle to players table.

USE `kahoot_btn`;

-- Columns for quiz table
ALTER TABLE `quiz`
  ADD COLUMN IF NOT EXISTS `game_type` ENUM('classic', 'obstacle') NOT NULL DEFAULT 'classic',
  ADD COLUMN IF NOT EXISTS `obstacle_image` VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `obstacle_answer` VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `total_pieces` INT NOT NULL DEFAULT 4;

-- Columns for questions table
ALTER TABLE `questions`
  ADD COLUMN IF NOT EXISTS `piece_index` INT NOT NULL DEFAULT 0;

-- Columns for room table
ALTER TABLE `room`
  ADD COLUMN IF NOT EXISTS `game_type` ENUM('classic', 'obstacle') NOT NULL DEFAULT 'classic',
  ADD COLUMN IF NOT EXISTS `opened_pieces` TEXT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `buzzer_player_id` BIGINT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `buzzer_status` ENUM('idle', 'buzzing', 'finished') NOT NULL DEFAULT 'idle',
  ADD COLUMN IF NOT EXISTS `buzzer_time` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `winner_player_id` BIGINT NULL DEFAULT NULL;

-- Columns for players table
ALTER TABLE `players`
  ADD COLUMN IF NOT EXISTS `unlocked_pieces` TEXT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `is_disqualified_obstacle` TINYINT(1) NOT NULL DEFAULT 0;
