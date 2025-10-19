-- Script SQL para a versão 2.0 do banco de dados.
-- Apague o banco de dados antigo e importe este arquivo.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `gestor_processos`
--
CREATE DATABASE IF NOT EXISTS `gestor_processos` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `gestor_processos`;

-- --------------------------------------------------------

--
-- Table structure for table `operators`
--

CREATE TABLE `operators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `badgeId` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `badgeId` (`badgeId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `operationId` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `sequence` int(11) NOT NULL,
  `description` text NOT NULL,
  `machine` varchar(255) DEFAULT NULL,
  `timeCentesimal` decimal(10,3) NOT NULL,
  `timeSeconds` decimal(10,3) NOT NULL,
  `operatorsReal` int(11) NOT NULL,
  `agrup` varchar(10) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pendente',
  `observation` text DEFAULT NULL,
  `assignedOperators` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assignedOperators`)),
  `layoutPosition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layoutPosition`)),
  `modelInfo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`modelInfo`)),
  PRIMARY KEY (`operationId`),
  KEY `model` (`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `role`) VALUES
('admin_user_01', 'ericsson.almeida', '$2y$10$9vP8a.UjX9eC9O.Jg6n8z.KkXvO.qXl6uH9jK3sN7oW4zT2kXyRz2', 'Ericsson Almeida', 'admin');

COMMIT;

