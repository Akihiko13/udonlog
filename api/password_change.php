<?php
// ログイン中にパスワードを変更する（現在のパスワードで本人確認）。
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in  = body();
$cur = (string)($in['current_password'] ?? '');
$new = (string)($in['new_password'] ?? '');

// 現在のパスワードを照合
$st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
$st->execute([$u['id']]);
$row = $st->fetch();
if (!$row) json_error('ユーザーが見つかりません', 404);
if (empty($row['password_hash'])) {
  json_error('このアカウントにはパスワードが設定されていません', 400);
}
if (!password_verify($cur, $row['password_hash'])) {
  json_error('現在のパスワードが違います', 401);
}

// 新パスワードの検証（register.php と同じ基準）
if (mb_strlen($new) < 8)   json_error('新しいパスワードは8文字以上にしてください');
if (mb_strlen($new) > 200) json_error('パスワードが長すぎます');
if (is_weak_password($new)) {
  json_error('このパスワードは推測されやすいため使えません。別のパスワードにしてください');
}
if (password_verify($new, $row['password_hash'])) {
  json_error('現在と同じパスワードです。別のパスワードにしてください');
}

$st = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$st->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);

json_out(['ok' => true]);
