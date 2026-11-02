-- Migration: Fix cascade delete for votes table when deleting quiz/questions/answers
-- Date: 2026-11-02

ALTER TABLE `votes` DROP FOREIGN KEY `FK_IDPLAYER_PLAYERS2`;

ALTER TABLE `votes`
  ADD CONSTRAINT `FK_IDPLAYER_PLAYERS2`
  FOREIGN KEY (`id_answer`) REFERENCES `answers` (`id_answer`)
  ON DELETE CASCADE;
