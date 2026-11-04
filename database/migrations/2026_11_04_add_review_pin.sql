-- Migration: Add review_pin column to quiz table for self-paced review mode
-- Date: 2026-11-04

USE `kahoot_btn`;

ALTER TABLE `quiz`
  ADD COLUMN IF NOT EXISTS `review_pin` VARCHAR(10) NULL DEFAULT NULL AFTER `is_review_mode`;
