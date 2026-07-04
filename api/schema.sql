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
  username      VARCHAR(20)  UNIQUE,                 -- プロフィールURL用の一意名（半角英数字・_、小文字保存。例: udolog.com/kagawan）
  password_hash VARCHAR(255) NULL,                   -- パスワード（OAuthのみの人はNULL）
  nickname      VARCHAR(50)  NOT NULL,
  city          VARCHAR(20)  NULL,                   -- 活動エリア（よく行くエリア）
  x_handle      VARCHAR(15)  NULL,                   -- Xのユーザー名（@なし）
  avatar        VARCHAR(255) NULL,                   -- プロフィール写真のファイル名（例: '12.jpg'。NULLなら頭文字アバター）
  bio           VARCHAR(500) NULL,                   -- 自己紹介文（プロフィールに表示）
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

-- パスワード再設定トークン（メールで送るリンク用。生のトークンは保存せずSHA-256ハッシュで保管）
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL,                  -- 生トークンのSHA-256（16進64文字）
  expires_at DATETIME     NOT NULL,                  -- 有効期限（発行から1時間）
  used_at    DATETIME     NULL,                      -- 使用済みなら日時（再利用防止）
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_token (token_hash),
  KEY idx_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 会員が選ぶ「推しのうどん屋」（プロフィールに表示。最大3軒・並び順あり）
CREATE TABLE IF NOT EXISTS favorite_shops (
  user_id    INT UNSIGNED     NOT NULL,
  shop_id    INT UNSIGNED     NOT NULL,                -- shops-data.js の id
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,      -- 表示順（0が先頭）
  created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, shop_id),
  KEY idx_user (user_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- みんなの投稿への「いいね」（会員×記録で1つ。記録削除・退会でCASCADE）
CREATE TABLE IF NOT EXISTS likes (
  user_id    INT UNSIGNED NOT NULL,
  log_id     INT UNSIGNED NOT NULL,                     -- logs.id
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, log_id),
  KEY idx_log (log_id),
  CONSTRAINT fk_like_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_like_log  FOREIGN KEY (log_id)  REFERENCES logs(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 投稿へのコメント（1投稿に複数）
CREATE TABLE IF NOT EXISTS comments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_id     INT UNSIGNED NOT NULL,                     -- logs.id（どの投稿へのコメントか）
  user_id    INT UNSIGNED NOT NULL,                     -- コメントした会員
  body       VARCHAR(140) NOT NULL,                     -- 本文（最大140文字。投稿のひとこと感想と統一）
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_log (log_id, id),                             -- 投稿ごとに新着順で引く用
  CONSTRAINT fk_cmt_log  FOREIGN KEY (log_id)  REFERENCES logs(id)  ON DELETE CASCADE,
  CONSTRAINT fk_cmt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- フォロー（誰が誰をフォローしているか）
CREATE TABLE IF NOT EXISTS follows (
  follower_id INT UNSIGNED NOT NULL,                    -- フォローする人
  followee_id INT UNSIGNED NOT NULL,                    -- フォローされる人
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, followee_id),               -- 同じ相手を二重フォローしない
  KEY idx_followee (followee_id),                       -- フォロワー数の集計用
  CONSTRAINT fk_follow_follower FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_follow_followee FOREIGN KEY (followee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 新規登録のメール確認コード（アカウント作成前に所有確認。メール単位で1件）
CREATE TABLE IF NOT EXISTS email_verifications (
  email      VARCHAR(255)     NOT NULL PRIMARY KEY,
  code_hash  CHAR(64)         NOT NULL,                  -- 6桁コードのSHA-256
  expires_at DATETIME         NOT NULL,                  -- 有効期限（発行から10分）
  attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,        -- 照合失敗回数（総当たり防止）
  created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
