-- Migration: Add Review Mode columns to quiz, room, and players tables
-- Date: 2026-11-02

USE `kahoot_btn`;

-- Add review mode configuration to quiz table
ALTER TABLE `quiz`
  ADD COLUMN IF NOT EXISTS `is_review_mode` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `review_start_time` DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `review_end_time` DATETIME NULL DEFAULT NULL;

-- Add review mode configuration to room table
ALTER TABLE `room`
  ADD COLUMN IF NOT EXISTS `is_review_mode` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `review_start_time` DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `review_end_time` DATETIME NULL DEFAULT NULL;

-- Add individual progress tracking to players table for review mode
ALTER TABLE `players`
  ADD COLUMN IF NOT EXISTS `current_question_index` INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `question_start_time` INT(11) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `is_finished` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `finished_at` DATETIME NULL DEFAULT NULL;
