-- Migration: Add quiz settings, question media, and streak columns used by the current app.
-- Date: 2026-10-29

USE `kahoot_btn`;

ALTER TABLE `questions`
  ADD COLUMN IF NOT EXISTS `question_type` ENUM('multiple_choice','text') NOT NULL DEFAULT 'multiple_choice',
  ADD COLUMN IF NOT EXISTS `media_type` ENUM('image','video') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `sort_order` INT(11) NOT NULL DEFAULT 0;

ALTER TABLE `answers`
  ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `room`
  ADD COLUMN IF NOT EXISTS `show_questions_on_players` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `question_limit` INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `question_order` TEXT DEFAULT NULL;

ALTER TABLE `players`
  ADD COLUMN IF NOT EXISTS `current_streak` INT(11) NOT NULL DEFAULT 0;

ALTER TABLE `votes`
  ADD COLUMN IF NOT EXISTS `streak_bonus` INT(11) NOT NULL DEFAULT 0;
