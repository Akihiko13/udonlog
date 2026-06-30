<?php
// 記録の写真を削除（ログイン必須・本人の記録のみ）。編集画面で「写真を消す」用。
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in = body();
$id = (int)($in['log_id'] ?? 0);
if ($id <= 0) json_error('記録が指定されていません');

// 自分の記録か確認
$st = db()->prepare('SELECT id FROM logs WHERE id = ? AND user_id = ?');
$st->execute([$id, $u['id']]);
if (!$st->fetch()) json_error('記録が見つかりません', 404);

$photo = __DIR__ . '/../uploads/records/' . $id . '.jpg';
if (is_file($photo)) @unlink($photo);

$st = db()->prepare('UPDATE logs SET photo_count = 0 WHERE id = ? AND user_id = ?');
$st->execute([$id, $u['id']]);

json_out(['ok' => true]);
