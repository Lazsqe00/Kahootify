-- Migration: Add buzzer_guess and update buzzer_status enum in room table for Obstacle Puzzle
-- Date: 2026-11-02

ALTER TABLE `room` 
  MODIFY COLUMN `buzzer_status` ENUM('idle','buzzing','pending_review','finished') NOT NULL DEFAULT 'idle';

ALTER TABLE `room` 
  ADD COLUMN `buzzer_guess` VARCHAR(255) DEFAULT NULL AFTER `buzzer_time`;
