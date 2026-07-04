<?php
// コメントを削除する（ログイン必須）。
// 削除できるのは「コメント本人」または「その投稿の持ち主」。
// 返り値: { ok, count: 残りのコメント数, logId }
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in = body();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) json_error('コメントが指定されていません');

// コメント＋その投稿の持ち主をまとめて取得
$st = db()->prepare(
  'SELECT c.id, c.log_id, c.user_id AS commenter, l.user_id AS post_owner
   FROM comments c JOIN logs l ON l.id = c.log_id WHERE c.id = ?'
);
$st->execute([$id]);
$row = $st->fetch();
if (!$row) json_error('コメントが見つかりません', 404);

$uid = (int)$u['id'];
if ($uid !== (int)$row['commenter'] && $uid !== (int)$row['post_owner']) {
  json_error('このコメントを削除する権限がありません', 403);
}

$st = db()->prepare('DELETE FROM comments WHERE id = ?');
$st->execute([$id]);

$logId = (int)$row['log_id'];
$st = db()->prepare('SELECT COUNT(*) AS c FROM comments WHERE log_id = ?');
$st->execute([$logId]);
$count = (int)($st->fetch()['c'] ?? 0);

json_out(['ok' => true, 'count' => $count, 'logId' => $logId]);
