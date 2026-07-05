<?php
// みんなの投稿への「いいね」をトグル（ログイン必須）。
// すでに「いいね」していれば取り消し、していなければ追加する。
// 返り値: { ok, liked: 0|1, count: その投稿の合計いいね数 }
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in     = body();
$log_id = (int)($in['log_id'] ?? 0);
if ($log_id <= 0) json_error('投稿が指定されていません');

// 対象の記録が存在するか（公開・非公開は問わない＝存在すればOK）＋持ち主を取得
$st = db()->prepare('SELECT id, user_id FROM logs WHERE id = ?');
$st->execute([$log_id]);
$log = $st->fetch();
if (!$log) json_error('対象の投稿が見つかりません', 404);
$ownerId = (int)$log['user_id'];

// すでに「いいね」しているか
$st = db()->prepare('SELECT 1 FROM likes WHERE user_id = ? AND log_id = ?');
$st->execute([$u['id'], $log_id]);
$already = (bool)$st->fetch();

if ($already) {
  $st = db()->prepare('DELETE FROM likes WHERE user_id = ? AND log_id = ?');
  $st->execute([$u['id'], $log_id]);
  $liked = 0;
} else {
  $st = db()->prepare('INSERT IGNORE INTO likes (user_id, log_id) VALUES (?, ?)');
  $st->execute([$u['id'], $log_id]);
  $liked = 1;
  // 投稿の持ち主に通知。同じ人・同じ投稿への「いいね」通知が既にあれば重複させない
  // （いいねの付け外しを繰り返しても通知が増えないように）
  $chk = db()->prepare('SELECT 1 FROM notifications WHERE user_id = ? AND actor_id = ? AND type = "like" AND log_id = ?');
  $chk->execute([$ownerId, $u['id'], $log_id]);
  if (!$chk->fetch()) notify($ownerId, (int)$u['id'], 'like', $log_id);
}

// 最新の合計いいね数
$st = db()->prepare('SELECT COUNT(*) AS c FROM likes WHERE log_id = ?');
$st->execute([$log_id]);
$count = (int)($st->fetch()['c'] ?? 0);

json_out(['ok' => true, 'liked' => $liked, 'count' => $count]);
