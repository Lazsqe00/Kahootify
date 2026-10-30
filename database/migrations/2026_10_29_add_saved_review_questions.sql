-- Migration: Let logged-in users save questions for later review and dashboard access.
-- Date: 2026-10-29

USE `kahoot_btn`;

CREATE TABLE IF NOT EXISTS `saved_review_questions` (
  `id_saved` BIGINT(11) NOT NULL AUTO_INCREMENT,
  `id_creator` BIGINT(6) DEFAULT NULL,
  `id_player` BIGINT(6) DEFAULT NULL,
  `id_question` BIGINT(6) NOT NULL,
  `saved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_saved`),
  UNIQUE KEY `uq_saved_creator_question` (`id_creator`, `id_question`),
  KEY `idx_saved_review_creator` (`id_creator`),
  KEY `idx_saved_review_question` (`id_question`),
  CONSTRAINT `fk_saved_review_creator` FOREIGN KEY (`id_creator`) REFERENCES `creators` (`id_creator`) ON DELETE CASCADE,
  CONSTRAINT `fk_saved_review_player` FOREIGN KEY (`id_player`) REFERENCES `players` (`id_player`) ON DELETE SET NULL,
  CONSTRAINT `fk_saved_review_question` FOREIGN KEY (`id_question`) REFERENCES `questions` (`id_question`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
