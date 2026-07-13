<?php
// 退会：自分のアカウントを完全に削除する（ログイン必須・パスワードで本人確認）
// users を削除すると logs / auth_identities / ratings は ON DELETE CASCADE で自動削除される。
// 写真ファイル（プロフィール・記録）はDB削除では消えないので、DBを消す前後で手動削除し、
// 最後にセッションを破棄する。
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in   = body();
$pass = (string)($in['password'] ?? '');

// 本人確認用にパスワードハッシュと写真ファイル名を取得
$st = db()->prepare('SELECT password_hash, avatar FROM users WHERE id = ?');
$st->execute([$u['id']]);
$row = $st->fetch();
if (!$row) json_error('ユーザーが見つかりません', 404);

// パスワードが設定されている場合は照合（誤操作・なりすまし防止）
if (!empty($row['password_hash'])) {
  if (!password_verify($pass, $row['password_hash'])) {
    json_error('パスワードが違います', 401);
  }
}

// 記録写真ファイルを削除（logs は次のDELETEでCASCADE消去されるため、消える前に対象log_idの写真を消す）
$st = db()->prepare('SELECT id FROM logs WHERE user_id = ?');
$st->execute([$u['id']]);
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $logId) {
  $rp = __DIR__ . '/../uploads/records/' . (int)$logId . '.jpg';
  if (is_file($rp)) @unlink($rp);
}

// 会員削除（関連データはCASCADEで自動削除）
$st = db()->prepare('DELETE FROM users WHERE id = ?');
$st->execute([$u['id']]);

// プロフィール写真ファイルを削除
if (!empty($row['avatar'])) {
  $path = __DIR__ . '/../uploads/avatars/' . $row['avatar'];
  if (is_file($path)) @unlink($path);
}

// ログイン保持トークンのクッキーを消す（DB行は users 削除の CASCADE で既に消える）
clear_remember_token();

// セッション破棄（ログアウト状態に）
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

json_out(['ok' => true]);
