-- Migration: Add question mode, wager settings, player lifecycle statuses, and question logs
-- Date: 2026-11-01

USE `kahoot_btn`;

ALTER TABLE `questions`
  ADD COLUMN IF NOT EXISTS `mode` VARCHAR(50) NOT NULL DEFAULT 'normal',
  ADD COLUMN IF NOT EXISTS `mode_config` TEXT NULL;

ALTER TABLE `room`
  ADD COLUMN IF NOT EXISTS `starting_score` INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `max_missed_wagers` INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `allow_late_join` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `eliminated_can_play_normal` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `game_config` TEXT NULL;

ALTER TABLE `players`
  ADD COLUMN IF NOT EXISTS `status` ENUM('ACTIVE', 'ELIMINATED', 'SPECTATOR') NOT NULL DEFAULT 'ACTIVE',
  ADD COLUMN IF NOT EXISTS `eliminated_at_question` BIGINT(6) NULL,
  ADD COLUMN IF NOT EXISTS `elimination_order` INT(11) NULL,
  ADD COLUMN IF NOT EXISTS `consecutive_missed_wagers` INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `last_seen_at` INT(11) NULL;

CREATE TABLE IF NOT EXISTS `player_question_logs` (
  `id_log` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `id_room` BIGINT(11) NOT NULL,
  `id_question` BIGINT(6) NOT NULL,
  `id_player` BIGINT(6) NOT NULL,
  `mode` VARCHAR(50) NOT NULL DEFAULT 'normal',
  `wager_tier` VARCHAR(20) DEFAULT NULL,
  `wager_amount` INT(11) NOT NULL DEFAULT 0,
  `delta_score` INT(11) NOT NULL DEFAULT 0,
  `points_change` INT(11) NOT NULL DEFAULT 0,
  `final_score` INT(11) NOT NULL DEFAULT 0,
  `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
  `player_status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  `is_sitting_out` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  UNIQUE KEY `uq_room_q_player` (`id_room`, `id_question`, `id_player`),
  KEY `idx_log_room_q` (`id_room`, `id_question`),
  KEY `idx_log_player` (`id_player`),
  CONSTRAINT `fk_log_room` FOREIGN KEY (`id_room`) REFERENCES `room` (`id_room`) ON DELETE CASCADE,
  CONSTRAINT `fk_log_question` FOREIGN KEY (`id_question`) REFERENCES `questions` (`id_question`) ON DELETE CASCADE,
  CONSTRAINT `fk_log_player` FOREIGN KEY (`id_player`) REFERENCES `players` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
