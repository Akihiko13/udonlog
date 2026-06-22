<?php
// ログイン：メール＋パスワード
require __DIR__ . '/lib.php';
require_post();
require_csrf();

$in    = body();
$email = trim((string)($in['email'] ?? ''));
$pass  = (string)($in['password'] ?? '');

$st = db()->prepare('SELECT id, password_hash, nickname, city FROM users WHERE email = ?');
$st->execute([$email]);
$u = $st->fetch();

// メールが無い／パスワード未設定（OAuthのみ）／不一致は、すべて同じ文言で返す（情報を漏らさない）
if (!$u || empty($u['password_hash']) || !password_verify($pass, $u['password_hash'])) {
  json_error('メールアドレスまたはパスワードが違います', 401);
}

login_user((int)$u['id']);
json_out([
  'ok'   => true,
  'user' => ['id' => (int)$u['id'], 'email' => $email, 'nickname' => $u['nickname'], 'city' => $u['city']],
  'csrf' => csrf_token(),
]);
