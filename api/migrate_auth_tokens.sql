-- ===========================================================================
-- ログイン保持（remember me）トークンの追加マイグレーション
-- ---------------------------------------------------------------------------
-- 本番DBに一度だけ実行してください（phpMyAdmin等で貼り付け実行）。
-- 既に schema.sql を流し直す場合はこのテーブルも含まれます（IF NOT EXISTS）。
--
-- 仕組み：ログイン時に selector:validator をクッキー（60日・httponly）に保存し、
-- validator は SHA-256 でハッシュ化してこの表に保存する（生値は保存しない）。
-- セッションがGCで消えても、このトークンからログインを自動復元する。
-- ===========================================================================
CREATE TABLE IF NOT EXISTS auth_tokens (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  selector       CHAR(18) NOT NULL,                    -- クッキー内の識別子（hex18）
  validator_hash CHAR(64) NOT NULL,                    -- 検証子の SHA-256（生値は保存しない）
  expires_at     DATETIME NOT NULL,                    -- 有効期限（既定60日）
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_agent     VARCHAR(255) NULL,                    -- 参考：発行時の端末情報
  UNIQUE KEY uq_selector (selector),
  KEY idx_user (user_id),
  KEY idx_expires (expires_at),
  CONSTRAINT fk_tok_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
