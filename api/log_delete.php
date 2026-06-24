<?php
// 記録を1件削除（ログイン必須・自分の記録のみ）
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in = body();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) json_error('記録が指定されていません');

// 自分（user_id）の記録だけを削除（他人の記録は消せない）
$st = db()->prepare('DELETE FROM logs WHERE id = ? AND user_id = ?');
$st->execute([$id, $u['id']]);

if ($st->rowCount() === 0) {
  json_error('削除できる記録が見つかりませんでした', 404);
}
json_out(['ok' => true, 'deleted' => $id]);
