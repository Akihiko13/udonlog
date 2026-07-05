<?php
// ログイン中ユーザーの未読通知をすべて既読にする。
// 返り値: { ok }
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$st = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
$st->execute([$u['id']]);

json_out(['ok' => true]);
