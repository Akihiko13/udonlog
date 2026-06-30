<?php
// プロフィール写真を削除して、頭文字アバターに戻す（ログイン必須）
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

// 現在のファイル名を取得して削除
$st = db()->prepare('SELECT avatar FROM users WHERE id = ?');
$st->execute([$u['id']]);
$row = $st->fetch();
$file = $row['avatar'] ?? null;

if ($file) {
  $path = __DIR__ . '/../uploads/avatars/' . $file;
  if (is_file($path)) @unlink($path);
}

$st = db()->prepare('UPDATE users SET avatar = NULL WHERE id = ?');
$st->execute([$u['id']]);

json_out(['ok' => true]);
