-- Anime Website database schema (MySQL 8.0.16+ / MariaDB with CHECK support)
-- Fresh-install script. Back up an existing database before running this file.
-- This script does not create a default administrator with a known password.

CREATE DATABASE IF NOT EXISTS anime_website
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE anime_website;
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password VARCHAR(255) NOT NULL COMMENT 'password_hash() output; never store plaintext',
  role TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=user, 2=moderator, 3=admin; 0 is reserved for guest sessions',
  profile_picture VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_created (role, created_at),
  CONSTRAINT chk_users_role CHECK (role BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anime (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  cover_image VARCHAR(255) DEFAULT NULL,
  banner_image VARCHAR(255) DEFAULT NULL,
  release_year YEAR DEFAULT NULL,
  status ENUM('مكتمل','مستمر','متوقف') NOT NULL DEFAULT 'مستمر',
  episodes_count INT UNSIGNED NOT NULL DEFAULT 0,
  rating DECIMAL(3,2) UNSIGNED NOT NULL DEFAULT 0.00,
  views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_anime_title (title),
  KEY idx_anime_status_year (status, release_year),
  KEY idx_anime_rating_views (rating, views),
  KEY idx_anime_created (created_at),
  CONSTRAINT chk_anime_rating CHECK (rating BETWEEN 0 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS genres (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  description TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_genres_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anime_genres (
  anime_id INT UNSIGNED NOT NULL,
  genre_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (anime_id, genre_id),
  KEY idx_anime_genres_genre (genre_id, anime_id),
  CONSTRAINT fk_anime_genres_anime FOREIGN KEY (anime_id) REFERENCES anime(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_anime_genres_genre FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS episodes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  anime_id INT UNSIGNED NOT NULL,
  episode_number INT UNSIGNED NOT NULL,
  title VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  thumbnail VARCHAR(255) DEFAULT NULL,
  video_path VARCHAR(1024) NOT NULL,
  duration INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Duration in seconds',
  views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  release_date DATE DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_episode_number_per_anime (anime_id, episode_number),
  KEY idx_episodes_release_date (release_date),
  KEY idx_episodes_views (views),
  CONSTRAINT fk_episodes_anime FOREIGN KEY (anime_id) REFERENCES anime(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_episode_number CHECK (episode_number > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  anime_id INT UNSIGNED DEFAULT NULL,
  episode_id INT UNSIGNED DEFAULT NULL,
  guest_name VARCHAR(80) DEFAULT NULL,
  content TEXT NOT NULL,
  status ENUM('pending','approved','rejected','deleted') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comments_status_created (status, created_at),
  KEY idx_comments_anime_status_created (anime_id, status, created_at),
  KEY idx_comments_episode_status_created (episode_id, status, created_at),
  KEY idx_comments_user_created (user_id, created_at),
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_comments_anime FOREIGN KEY (anime_id) REFERENCES anime(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_comments_episode FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_comments_target CHECK (anime_id IS NOT NULL OR episode_id IS NOT NULL),
  CONSTRAINT chk_comments_author CHECK (user_id IS NOT NULL OR (guest_name IS NOT NULL AND CHAR_LENGTH(TRIM(guest_name)) > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ratings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  anime_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ratings_user_anime (user_id, anime_id),
  KEY idx_ratings_anime_rating (anime_id, rating),
  CONSTRAINT fk_ratings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ratings_anime FOREIGN KEY (anime_id) REFERENCES anime(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_ratings_value CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS watchlist (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  anime_id INT UNSIGNED NOT NULL,
  status ENUM('قيد المشاهدة','مكتمل','في قائمة الانتظار') NOT NULL DEFAULT 'في قائمة الانتظار',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_watchlist_user_anime (user_id, anime_id),
  KEY idx_watchlist_user_status_updated (user_id, status, updated_at),
  KEY idx_watchlist_anime (anime_id),
  CONSTRAINT fk_watchlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_watchlist_anime FOREIGN KEY (anime_id) REFERENCES anime(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Safe to re-run: predefined genres only are inserted if absent.
INSERT IGNORE INTO genres (name, description) VALUES
 ('أكشن','أنمي يحتوي على مشاهد قتالية ومغامرات مثيرة'),
 ('مغامرة','أنمي يركز على الرحلات والاستكشاف'),
 ('كوميدي','أنمي يحتوي على مواقف فكاهية'),
 ('دراما','أنمي يركز على القصص العاطفية والمؤثرة'),
 ('خيال','أنمي يحتوي على عناصر خيالية'),
 ('رعب','أنمي يهدف إلى إثارة الخوف والرهبة'),
 ('غموض','أنمي يحتوي على ألغاز وأسرار'),
 ('رومانسي','أنمي يركز على العلاقات العاطفية'),
 ('خيال علمي','أنمي يتضمن عناصر علمية وتكنولوجية'),
 ('رياضي','أنمي يركز على الرياضة والمنافسات'),
 ('مدرسي','أنمي تدور أحداثه في بيئة مدرسية');

-- Recreate derived-value triggers to avoid stale aggregates after updates/deletes.
DROP TRIGGER IF EXISTS trg_ratings_after_insert;
DROP TRIGGER IF EXISTS trg_ratings_after_update;
DROP TRIGGER IF EXISTS trg_ratings_after_delete;
DROP TRIGGER IF EXISTS trg_episodes_after_insert;
DROP TRIGGER IF EXISTS trg_episodes_after_update;
DROP TRIGGER IF EXISTS trg_episodes_after_delete;
DROP PROCEDURE IF EXISTS update_anime_rating;
DROP PROCEDURE IF EXISTS update_episodes_count;

DELIMITER $$
CREATE PROCEDURE update_anime_rating(IN p_anime_id INT UNSIGNED)
BEGIN
  UPDATE anime
     SET rating = COALESCE((SELECT AVG(r.rating) FROM ratings r WHERE r.anime_id = p_anime_id), 0)
   WHERE id = p_anime_id;
END$$

CREATE PROCEDURE update_episodes_count(IN p_anime_id INT UNSIGNED)
BEGIN
  UPDATE anime
     SET episodes_count = (SELECT COUNT(*) FROM episodes e WHERE e.anime_id = p_anime_id)
   WHERE id = p_anime_id;
END$$

CREATE TRIGGER trg_ratings_after_insert AFTER INSERT ON ratings FOR EACH ROW
BEGIN CALL update_anime_rating(NEW.anime_id); END$$
CREATE TRIGGER trg_ratings_after_update AFTER UPDATE ON ratings FOR EACH ROW
BEGIN
  CALL update_anime_rating(OLD.anime_id);
  IF NEW.anime_id <> OLD.anime_id THEN CALL update_anime_rating(NEW.anime_id); END IF;
END$$
CREATE TRIGGER trg_ratings_after_delete AFTER DELETE ON ratings FOR EACH ROW
BEGIN CALL update_anime_rating(OLD.anime_id); END$$

CREATE TRIGGER trg_episodes_after_insert AFTER INSERT ON episodes FOR EACH ROW
BEGIN CALL update_episodes_count(NEW.anime_id); END$$
CREATE TRIGGER trg_episodes_after_update AFTER UPDATE ON episodes FOR EACH ROW
BEGIN
  CALL update_episodes_count(OLD.anime_id);
  IF NEW.anime_id <> OLD.anime_id THEN CALL update_episodes_count(NEW.anime_id); END IF;
END$$
CREATE TRIGGER trg_episodes_after_delete AFTER DELETE ON episodes FOR EACH ROW
BEGIN CALL update_episodes_count(OLD.anime_id); END$$
DELIMITER ;

-- Create the first administrator using a PHP password_hash() generated hash.
-- Do not seed a public/default admin password in this SQL file.
