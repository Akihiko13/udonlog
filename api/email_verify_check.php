<?php
// 新規登録：確認コードを照合する。成功するとセッションに「確認済みメール」を記録し、
// 以降の register.php がそのメールでのみ登録を許可する。
require __DIR__ . '/lib.php';
require_post();
require_csrf();

const VERIFY_MAX_ATTEMPTS = 5;   // 照合失敗の上限（総当たり防止）

$in    = body();
$email = trim((string)($in['email'] ?? ''));
$code  = trim((string)($in['code'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('メールアドレスの形式が正しくありません');
if (!preg_match('/^\d{6}$/', $code))            json_error('確認コードは6桁の数字です');

$st = db()->prepare('SELECT code_hash, attempts, (expires_at > NOW()) AS valid FROM email_verifications WHERE email = ?');
$st->execute([$email]);
$row = $st->fetch();

if (!$row || !$row['valid']) {
  json_error('確認コードの有効期限が切れています。お手数ですが、コードを再送してください。', 410);
}
if ((int)$row['attempts'] >= VERIFY_MAX_ATTEMPTS) {
  json_error('試行回数の上限に達しました。コードを再送してください。', 429);
}

// 照合（タイミング攻撃に配慮して hash_equals）
if (!hash_equals($row['code_hash'], hash('sha256', $code))) {
  $st = db()->prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE email = ?');
  $st->execute([$email]);
  json_error('確認コードが正しくありません。');
}

// 成功：このメールを「確認済み」としてセッションに記録
$_SESSION['verified_email'] = $email;

json_out(['ok' => true]);
