<?php
// ===========================================================================
// 共通ライブラリ：設定読込・DB接続・セッション・JSON・CSRF・認証
// すべてのAPIファイルの先頭で require します。
// ===========================================================================
declare(strict_types=1);

$CONFIG = require __DIR__ . '/config.php';

// --- セッション（安全なCookie設定）---
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,       // JSからCookieを読めない（XSS対策）
  'secure'   => $https,     // HTTPSのみ送信
  'samesite' => 'Lax',      // 別サイトからのPOSTを抑止（CSRF対策の一助）
]);
session_name('udolog_sid');
session_start();

// --- DB接続（PDO・プリペアドステートメント）---
function db(): PDO {
  static $pdo = null;
  global $CONFIG;
  if ($pdo === null) {
    $dsn = "mysql:host={$CONFIG['db_host']};dbname={$CONFIG['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $CONFIG['db_user'], $CONFIG['db_pass'], [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,   // 本物のプリペアドステートメント
    ]);
  }
  return $pdo;
}

// --- JSON出力 ---
function json_out($data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function json_error(string $msg, int $status = 400): void {
  json_out(['ok' => false, 'error' => $msg], $status);
}

// --- リクエストのJSONボディを配列で取得 ---
function body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === '' || $raw === false) return [];
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

// --- POSTメソッド強制 ---
function require_post(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_error('POSTでアクセスしてください', 405);
}

// --- CSRFトークン ---
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}
function require_csrf(): void {
  $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
    json_error('セッションが無効です。ページを再読み込みしてください。', 419);
  }
}

// --- ありがちで危険なパスワードか判定（登録・パスワード再設定で共通利用）---
function is_weak_password(string $pass): bool {
  static $list = [
    'password','password1','password123','passw0rd','12345678','123456789','1234567890',
    'qwerty','qwertyui','qwerty123','11111111','00000000','88888888','12341234',
    'abcd1234','abc12345','1q2w3e4r','1qaz2wsx','asdfghjk','zxcvbnm','qazwsxedc',
    'iloveyou','letmein','letmein1','welcome','welcome1','admin123','administrator',
    'sunshine','princess','football','baseball','monkey12','dragon12','starwars',
    'trustno1','superman','batman12','master12','shadow12','google12','whatever',
    'udonudon','udon1234','sanuki','sanukiudon','udolog','udolog123','kagawa1234',
  ];
  return in_array(mb_strtolower($pass), $list, true);
}

// --- アバター画像のURLを組み立てる ---
// DBには avatar カラムにファイル名（例: '12.jpg'）だけを保存し、表示用URLはここで生成。
// ファイルの更新時刻を ?v= に付けてブラウザキャッシュを確実に更新する。
function avatar_url(?string $file): ?string {
  if (!$file) return null;
  $path = __DIR__ . '/../uploads/avatars/' . $file;
  $v = @filemtime($path);
  return 'uploads/avatars/' . $file . ($v ? ('?v=' . $v) : '');
}

// --- 記録写真のURLを組み立てる（uploads/records/{logId}.jpg。無ければnull）---
function record_photo_url(int $logId): ?string {
  $path = __DIR__ . '/../uploads/records/' . $logId . '.jpg';
  if (!is_file($path)) return null;
  return 'uploads/records/' . $logId . '.jpg?v=' . filemtime($path);
}

// --- ユーザー名（プロフィールURL /username 用）---
// 形式：半角英数字とアンダースコア、3〜20文字。大文字小文字は区別しない（小文字で保存・比較）。
function normalize_username(string $name): string {
  return strtolower(trim($name));
}
// 予約語（実在ページ・ディレクトリ・将来使いそうな語）。ユーザー名には使わせない。
function is_reserved_username(string $name): bool {
  static $reserved = [
    'admin','api','app','about','account','assets','auth','blog','contact','css','faq',
    'favicon','forgot-password','help','home','images','img','index','info','js','login',
    'logout','mail','me','mypage','news','privacy','profile','profile-edit','record',
    'register','reset-password','robots','root','search','settings','shop','shops','shop-detail',
    'signin','signup','sitemap','static','support','terms','test','udolog','uploads','user',
    'users','www',
  ];
  return in_array($name, $reserved, true);
}
// 妥当なユーザー名か検査。OKなら null、NGならエラーメッセージを返す。
function username_error(string $name): ?string {
  if ($name === '') return 'ユーザー名を入力してください';
  if (!preg_match('/^[a-z0-9_]{3,20}$/', $name)) {
    return 'ユーザー名は半角英数字とアンダースコア（_）で3〜20文字にしてください';
  }
  if (is_reserved_username($name)) return 'このユーザー名は使用できません';
  return null;
}
// ニックネーム等から仮のユーザー名を自動生成（Google登録用。重複しない候補を返す）。
function generate_username(PDO $pdo, string $seed): string {
  $base = preg_replace('/[^a-z0-9_]/', '', strtolower($seed));
  if (strlen($base) < 3) $base = 'user';
  $base = substr($base, 0, 15);
  if (is_reserved_username($base)) $base = 'user_' . $base;
  $chk = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
  for ($i = 0; $i < 50; $i++) {
    $cand = ($i === 0) ? $base : $base . $i;
    $chk->execute([$cand]);
    if (!$chk->fetchColumn()) return $cand;
  }
  return $base . bin2hex(random_bytes(3));   // 万一の保険
}

// --- 認証ヘルパー ---
function current_user(): ?array {
  if (empty($_SESSION['uid'])) return null;
  $st = db()->prepare('SELECT id, email, username, nickname, city, x_handle, avatar, bio, password_hash FROM users WHERE id = ?');
  $st->execute([$_SESSION['uid']]);
  $u = $st->fetch();
  if (!$u) return null;
  $u['id'] = (int)$u['id'];
  $u['avatarUrl']   = avatar_url($u['avatar'] ?? null);   // 表示用URL（未設定なら null）
  $u['hasPassword'] = !empty($u['password_hash']);        // パスワードを持つか（Google専用ならfalse）
  unset($u['avatar']);                                     // 生ファイル名はフロントに渡さない
  unset($u['password_hash']);                              // ハッシュはフロントに渡さない
  return $u;
}
function require_login(): array {
  $u = current_user();
  if (!$u) json_error('ログインが必要です', 401);
  return $u;
}
function login_user(int $uid): void {
  session_regenerate_id(true);   // ログイン時にセッションID再発行（固定化対策）
  $_SESSION['uid'] = $uid;
}

// --- 通知 ---
// 受け取る人($userId)へ、行動した人($actorId)による通知を1件作る。
// 自分自身の行動は通知しない。通知の失敗は本処理を止めない（握りつぶす）。
function notify(int $userId, int $actorId, string $type, ?int $logId = null): void {
  if ($userId <= 0 || $userId === $actorId) return;
  try {
    $st = db()->prepare(
      'INSERT INTO notifications (user_id, actor_id, type, log_id) VALUES (?, ?, ?, ?)'
    );
    $st->execute([$userId, $actorId, $type, $logId]);
  } catch (\Throwable $e) {
    // notifications テーブル未作成などでも本処理（コメント等）は成功させる
  }
}

// 未読の通知数（ログイン中ユーザー）
function unread_notif_count(int $userId): int {
  if ($userId <= 0) return 0;
  try {
    $st = db()->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int)($st->fetch()['c'] ?? 0);
  } catch (\Throwable $e) {
    return 0;
  }
}
