-- ===========================================================================
-- コメント機能の追加マイグレーション
-- ---------------------------------------------------------------------------
-- 本番DBに一度だけ実行してください（phpMyAdmin等で貼り付け実行）。
-- 既に schema.sql を流し直す場合はこのテーブルも含まれます（IF NOT EXISTS）。
-- ===========================================================================
CREATE TABLE IF NOT EXISTS comments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_id     INT UNSIGNED NOT NULL,                     -- logs.id（どの投稿へのコメントか）
  user_id    INT UNSIGNED NOT NULL,                     -- コメントした会員
  body       VARCHAR(300) NOT NULL,                     -- 本文（最大300文字）
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_log (log_id, id),                             -- 投稿ごとに新着順で引く用
  CONSTRAINT fk_cmt_log  FOREIGN KEY (log_id)  REFERENCES logs(id)  ON DELETE CASCADE,
  CONSTRAINT fk_cmt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
