-- ===========================================================================
-- 通知機能の追加マイグレーション
-- ---------------------------------------------------------------------------
-- 本番DBに一度だけ実行してください（phpMyAdmin等で貼り付け実行）。
-- 既に schema.sql を流し直す場合はこのテーブルも含まれます（IF NOT EXISTS）。
-- ※ comments / follows / likes テーブルが先に存在している必要があります。
-- ===========================================================================
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,      -- 通知を受け取る人
  actor_id   INT UNSIGNED NOT NULL,      -- 行動した人（コメント/フォロー/いいねをした人）
  type       VARCHAR(16) NOT NULL,       -- 'comment' | 'follow' | 'like'
  log_id     INT UNSIGNED NULL,          -- comment/like の対象投稿（follow は NULL）
  is_read    TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user   (user_id, id),          -- 新着順に引く用
  KEY idx_unread (user_id, is_read),     -- 未読数の集計用
  CONSTRAINT fk_notif_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_log   FOREIGN KEY (log_id)   REFERENCES logs(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
