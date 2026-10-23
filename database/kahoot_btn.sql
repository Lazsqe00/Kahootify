
CREATE DATABASE IF NOT EXISTS `kahoot_btn` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kahoot_btn`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id_role` bigint(6) NOT NULL AUTO_INCREMENT,
  `name_role` varchar(50) NOT NULL,
  PRIMARY KEY (`id_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id_role`, `name_role`) VALUES
(1, 'Teacher'),
(2, 'Student'),
(3, 'Socially'),
(4, 'Work');

DROP TABLE IF EXISTS `creators`;
CREATE TABLE `creators` (
  `id_creator` bigint(6) NOT NULL AUTO_INCREMENT,
  `role` bigint(6) NOT NULL DEFAULT 1,
  `username` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(65) NOT NULL,
  `password` varchar(600) NOT NULL DEFAULT '0',
  `token` varchar(500) DEFAULT NULL,
  `confirmation_email` varchar(10) DEFAULT '1',
  `useradd` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `userupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_creator`),
  KEY `FK_Role_creator` (`role`),
  CONSTRAINT `FK_Role_creator` FOREIGN KEY (`role`) REFERENCES `roles` (`id_role`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `creators` (`id_creator`, `role`, `username`, `name`, `email`, `password`, `confirmation_email`) VALUES
(1, 1, 'admin', 'Admin Teacher', 'admin@kahoot.local', 'ba3253876aed6bc22d4a6ff53d8406c6ad864195ed144ab5c87621b6c233b548baeae6956df346ec8c17f5ea10f35ee3cbc514797ed7ddd3145464e2a0bab413', '1'),
(2, 1, 'teacher', 'Teacher John', 'teacher@kahoot.local', 'ba3253876aed6bc22d4a6ff53d8406c6ad864195ed144ab5c87621b6c233b548baeae6956df346ec8c17f5ea10f35ee3cbc514797ed7ddd3145464e2a0bab413', '1');

DROP TABLE IF EXISTS `quiz`;
CREATE TABLE `quiz` (
  `id_quiz` bigint(6) NOT NULL AUTO_INCREMENT,
  `id_creator` bigint(6) NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quizadd` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_quiz`),
  KEY `id_creator` (`id_creator`),
  CONSTRAINT `FK_CREATOR_QUIZ` FOREIGN KEY (`id_creator`) REFERENCES `creators` (`id_creator`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `quiz` (`id_quiz`, `id_creator`, `name`, `description`) VALUES
(1, 1, 'IT & Technology Trivia', 'General knowledge quiz on computer science and programming');

DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id_question` bigint(6) NOT NULL AUTO_INCREMENT,
  `id_quiz` bigint(11) NOT NULL,
  `text` varchar(255) DEFAULT NULL,
  `points` int(5) NOT NULL DEFAULT 1000,
  `time_limit` int(11) NOT NULL DEFAULT 20,
  `image` varchar(255) DEFAULT NULL,
  `questionadd` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `questionupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `timestart` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `timeend` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_question`),
  KEY `fmquestion` (`id_quiz`),
  CONSTRAINT `fmquestion` FOREIGN KEY (`id_quiz`) REFERENCES `quiz` (`id_quiz`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `questions` (`id_question`, `id_quiz`, `text`, `points`, `time_limit`, `image`) VALUES
(1, 1, 'Which programming language runs natively in web browsers?', 1000, 20, NULL),
(2, 1, 'PHP is a recursive acronym for what?', 1000, 20, NULL);

DROP TABLE IF EXISTS `answers`;
CREATE TABLE `answers` (
  `id_answer` bigint(6) NOT NULL AUTO_INCREMENT,
  `id_question` bigint(6) NOT NULL DEFAULT 0,
  `is_correct` int(11) NOT NULL DEFAULT 0,
  `text` varchar(300) NOT NULL,
  `answeradd` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `answerupdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_answer`),
  KEY `id_question` (`id_question`),
  CONSTRAINT `FK_QUESTION_ANSWER` FOREIGN KEY (`id_question`) REFERENCES `questions` (`id_question`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `answers` (`id_answer`, `id_question`, `is_correct`, `text`) VALUES
(1, 1, 0, 'C++'),
(2, 1, 1, 'JavaScript'),
(3, 1, 0, 'Python'),
(4, 1, 0, 'Java');

INSERT INTO `answers` (`id_answer`, `id_question`, `is_correct`, `text`) VALUES
(5, 2, 1, 'PHP: Hypertext Preprocessor'),
(6, 2, 0, 'Personal Home Page'),
(7, 2, 0, 'Private Home Page'),
(8, 2, 0, 'Preprocessed Hypertext Processor');

DROP TABLE IF EXISTS `room`;
CREATE TABLE `room` (
  `id_room` bigint(11) NOT NULL AUTO_INCREMENT,
  `id_quiz` bigint(11) NOT NULL,
  `id_creator` bigint(11) NOT NULL,
  `pin` varchar(20) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'waiting',
  `question_start_time` int(11) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `roomadd` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `roomupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_room`),
  KEY `room1` (`id_quiz`),
  KEY `room2` (`id_creator`),
  CONSTRAINT `room1` FOREIGN KEY (`id_quiz`) REFERENCES `quiz` (`id_quiz`) ON DELETE CASCADE,
  CONSTRAINT `room2` FOREIGN KEY (`id_creator`) REFERENCES `creators` (`id_creator`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `id_player` bigint(6) NOT NULL AUTO_INCREMENT,
  `id_room` bigint(20) NOT NULL,
  `nickname` varchar(50) NOT NULL DEFAULT 'ANONYMOUS',
  `score` int(6) DEFAULT 0,
  `answers_correct` int(6) DEFAULT 0,
  `character_id` varchar(50) NOT NULL DEFAULT '1500',
  `accessory_id` varchar(50) NOT NULL DEFAULT '',
  `is_kicked` tinyint(1) NOT NULL DEFAULT 0,
  `playeradd` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `playerup` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_player`),
  KEY `pla1` (`id_room`),
  CONSTRAINT `pla1` FOREIGN KEY (`id_room`) REFERENCES `room` (`id_room`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `votes`;
CREATE TABLE `votes` (
  `id_vote` bigint(6) NOT NULL AUTO_INCREMENT,
  `id_player` bigint(6) NOT NULL,
  `id_answer` bigint(20) NOT NULL,
  `rankingadd` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rankingup` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `points_earned` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_vote`),
  KEY `id_player` (`id_player`),
  KEY `FK_IDPLAYER_PLAYERS2` (`id_answer`),
  CONSTRAINT `FK_IDPLAYER_PLAYERS` FOREIGN KEY (`id_player`) REFERENCES `players` (`id_player`) ON DELETE CASCADE,
  CONSTRAINT `FK_IDPLAYER_PLAYERS2` FOREIGN KEY (`id_answer`) REFERENCES `answers` (`id_answer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
