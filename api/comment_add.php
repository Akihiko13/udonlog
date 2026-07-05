<?php
// 投稿(log)にコメントを追加する（ログイン必須）。
// 非公開投稿には、その投稿の持ち主だけがコメントできる。
// 返り値: { ok, comment: {...}, count: その投稿の合計コメント数 }
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in     = body();
$log_id = (int)($in['log_id'] ?? 0);
$text   = trim((string)($in['body'] ?? ''));
if ($log_id <= 0) json_error('投稿が指定されていません');
if ($text === '') json_error('コメントを入力してください');
if (mb_strlen($text) > 140) json_error('コメントは140文字までです');

// 対象投稿の存在＋コメント可否（非公開は本人のみ）
$st = db()->prepare('SELECT id, user_id, is_public FROM logs WHERE id = ?');
$st->execute([$log_id]);
$log = $st->fetch();
if (!$log) json_error('対象の投稿が見つかりません', 404);
if ((int)$log['is_public'] !== 1 && (int)$u['id'] !== (int)$log['user_id']) {
  json_error('この投稿にはコメントできません', 403);
}

$st = db()->prepare('INSERT INTO comments (log_id, user_id, body) VALUES (?, ?, ?)');
$st->execute([$log_id, $u['id'], $text]);
$cid = (int)db()->lastInsertId();

// 投稿の持ち主に通知（自分の投稿へのコメントは通知しない＝notify内で判定）
notify((int)$log['user_id'], (int)$u['id'], 'comment', $log_id);

// 合計コメント数
$st = db()->prepare('SELECT COUNT(*) AS c FROM comments WHERE log_id = ?');
$st->execute([$log_id]);
$count = (int)($st->fetch()['c'] ?? 0);

// 追加したコメント（投稿者情報つき）を返す。自分の投稿なので canDelete=true。
$comment = [
  'id'        => $cid,
  'userId'    => (int)$u['id'],
  'username'  => $u['username'],
  'nickname'  => $u['nickname'],
  'avatarUrl' => $u['avatarUrl'] ?? null,
  'body'      => $text,
  'createdAt' => date('Y-m-d H:i:s'),
  'canDelete' => true,
];

json_out(['ok' => true, 'comment' => $comment, 'count' => $count]);
