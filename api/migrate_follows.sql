-- ===========================================================================
-- フォロー機能の追加マイグレーション
-- ---------------------------------------------------------------------------
-- 本番DBに一度だけ実行してください（phpMyAdmin等で貼り付け実行）。
-- 既に schema.sql を流し直す場合はこのテーブルも含まれます（IF NOT EXISTS）。
-- ===========================================================================
CREATE TABLE IF NOT EXISTS follows (
  follower_id INT UNSIGNED NOT NULL,                    -- フォローする人
  followee_id INT UNSIGNED NOT NULL,                    -- フォローされる人
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, followee_id),               -- 同じ相手を二重フォローしない
  KEY idx_followee (followee_id),                       -- フォロワー数の集計用
  CONSTRAINT fk_follow_follower FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_follow_followee FOREIGN KEY (followee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
