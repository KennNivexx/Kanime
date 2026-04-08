-- phpMyAdmin SQL Dump
-- Database: `projek_anime`

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Table structure for table `admin`
--

CREATE TABLE IF NOT EXISTS `admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
-- password is 'admin123'
--

INSERT IGNORE INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

--
-- Table structure for table `anime`
--

CREATE TABLE IF NOT EXISTS `anime` (
  `id` int NOT NULL AUTO_INCREMENT,
  `judul` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `rating` decimal(3,1) NOT NULL DEFAULT '0.0',
  `jumlah_episode` int NOT NULL DEFAULT '0',
  `genre` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sinopsis` text COLLATE utf8mb4_general_ci,
  `cover_image` longblob DEFAULT NULL,
  `rekomendasi` tinyint(1) DEFAULT '0',
  `urutan_rekomendasi` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




--
-- Table structure for table `episode`
--

CREATE TABLE IF NOT EXISTS `episode` (
  `id` int NOT NULL AUTO_INCREMENT,
  `anime_id` int NOT NULL,
  `nomor_episode` int NOT NULL,
  `judul_episode` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `link_video` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_eps` (`anime_id`,`nomor_episode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Constraints for table `episode`
--
ALTER TABLE `episode`
  ADD CONSTRAINT `episode_ibfk_1` FOREIGN KEY (`anime_id`) REFERENCES `anime` (`id`) ON DELETE CASCADE;

COMMIT;
