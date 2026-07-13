<?php
// パスワード再設定の実行：メールのリンクに含まれるトークンと新しいパスワードを受け取る。
require __DIR__ . '/lib.php';
require_post();
require_csrf();

$in    = body();
$token = trim((string)($in['token'] ?? ''));
$pass  = (string)($in['password'] ?? '');

// 新パスワードの検証（register.php と同じ基準）
if (mb_strlen($pass) < 8)   json_error('パスワードは8文字以上にしてください');
if (mb_strlen($pass) > 200) json_error('パスワードが長すぎます');
if (is_weak_password($pass)) {
  json_error('このパスワードは推測されやすいため使えません。別のパスワードにしてください');
}

// トークンが空、または明らかに形式不正なら拒否
if ($token === '' || !preg_match('/^[0-9a-f]{64}$/', $token)) {
  json_error('リンクが正しくありません。お手数ですが再度お試しください。');
}

// ハッシュで照合（有効期限内・未使用のものを探す）
$hash = hash('sha256', $token);
$st = db()->prepare(
  'SELECT id, user_id FROM password_resets
   WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
   ORDER BY id DESC LIMIT 1'
);
$st->execute([$hash]);
$row = $st->fetch();
if (!$row) {
  json_error('リンクの有効期限が切れているか、すでに使用されています。お手数ですが、もう一度パスワード再設定をやり直してください。', 410);
}

$uid = (int)$row['user_id'];

// パスワード更新＋このトークンを使用済みに（同一ユーザーの他トークンも無効化）
$pdo = db();
$pdo->beginTransaction();
try {
  $st = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
  $st->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);

  $st = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
  $st->execute([(int)$row['id']]);

  // 念のため、このユーザーの未使用トークンも無効化（使い回し防止）
  $st = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
  $st->execute([$uid]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  json_error('パスワードの更新に失敗しました。時間をおいて再度お試しください。', 500);
}

// ログイン保持トークンを全失効（再設定＝乗っ取り懸念があるため全端末を締め出す）
clear_all_remember_tokens($uid);

json_out(['ok' => true]);
