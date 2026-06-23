-- ===========================================================================
-- うどログ 会員機能 DBスキーマ（MySQL / Xサーバー）
-- ---------------------------------------------------------------------------
-- 使い方：XサーバーのphpMyAdminでこのSQLを実行するとテーブルが作成されます。
-- 文字コードは utf8mb4（絵文字・全角OK）。
-- ===========================================================================
SET NAMES utf8mb4;

-- 会員
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(255) UNIQUE,                 -- メール（OAuth専用化に備えNULL許容）
  password_hash VARCHAR(255) NULL,                   -- パスワード（OAuthのみの人はNULL）
  nickname      VARCHAR(50)  NOT NULL,
  city          VARCHAR(20)  NULL,                   -- 活動エリア（よく行くエリア）
  x_handle      VARCHAR(15)  NULL,                   -- Xのユーザー名（@なし）
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ログイン手段（将来のGoogle/X連携に対応。1会員が複数手段を持てる）
CREATE TABLE IF NOT EXISTS auth_identities (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  provider     VARCHAR(20)  NOT NULL,                -- 'password' / 'google' / 'x'
  provider_uid VARCHAR(255) NOT NULL,                -- passwordはメール、OAuthは各IDのsub等
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_provider (provider, provider_uid),
  KEY idx_user (user_id),
  CONSTRAINT fk_ai_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- うどんの記録（1杯=1行）
CREATE TABLE IF NOT EXISTS logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  shop_id     INT UNSIGNED NOT NULL,                 -- shops-data.js の id
  menus       VARCHAR(255) NULL,                     -- メニュー（・区切り）
  comment     TEXT NULL,
  visit_date  DATE NULL,
  photo_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_public   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_shop (shop_id),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- お店のおすすめ度（ユーザー×店で1つ・上書き）
CREATE TABLE IF NOT EXISTS ratings (
  user_id    INT UNSIGNED NOT NULL,
  shop_id    INT UNSIGNED NOT NULL,
  score      TINYINT UNSIGNED NOT NULL,              -- 1〜5
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, shop_id),
  CONSTRAINT fk_rat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
