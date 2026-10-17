-- ONE-TIME migration for an existing database created with the previous project SQL.
-- BACK UP the database first. Run this file once only, after selecting anime_website.
USE anime_website;

-- The current PHP application supports both registered and guest comments.
ALTER TABLE comments
  MODIFY COLUMN user_id INT NULL,
  ADD COLUMN guest_name VARCHAR(80) NULL AFTER episode_id,
  ADD COLUMN status ENUM('pending','approved','rejected','deleted') NOT NULL DEFAULT 'pending' AFTER content;

CREATE INDEX idx_comments_status_created ON comments (status, created_at);
CREATE INDEX idx_comments_anime_status_created ON comments (anime_id, status, created_at);
CREATE INDEX idx_comments_episode_status_created ON comments (episode_id, status, created_at);

-- Preserve existing comments as approved so previously visible comments do not disappear.
UPDATE comments SET status = 'approved';

-- Recalculate stored aggregates from their source records.
UPDATE anime a
SET a.rating = COALESCE((SELECT AVG(r.rating) FROM ratings r WHERE r.anime_id = a.id), 0),
    a.episodes_count = (SELECT COUNT(*) FROM episodes e WHERE e.anime_id = a.id);

-- Existing rating and episode insert/delete triggers from the original schema remain active.
-- This update trigger keeps episode counts correct if an episode is moved between anime.
DROP TRIGGER IF EXISTS after_episode_update;
DELIMITER $$
CREATE TRIGGER after_episode_update AFTER UPDATE ON episodes FOR EACH ROW
BEGIN
  UPDATE anime SET episodes_count = (SELECT COUNT(*) FROM episodes WHERE anime_id = OLD.anime_id)
   WHERE id = OLD.anime_id;
  IF NEW.anime_id <> OLD.anime_id THEN
    UPDATE anime SET episodes_count = (SELECT COUNT(*) FROM episodes WHERE anime_id = NEW.anime_id)
     WHERE id = NEW.anime_id;
  END IF;
END$$
DELIMITER ;
