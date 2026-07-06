<?php
// あるユーザーへのフォローをトグル（ログイン必須）。
// すでにフォローしていれば解除、していなければフォローする。
// 返り値: { ok, following: 0|1, followerCount: 対象の最新フォロワー数 }
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in     = body();
$target = (int)($in['user_id'] ?? 0);
// user_id が無ければ username から解決（連番ID露出を避けたい画面用）
if ($target <= 0 && !empty($in['username'])) {
  $st = db()->prepare('SELECT id FROM users WHERE username = ?');
  $st->execute([normalize_username((string)$in['username'])]);
  $row = $st->fetch();
  if ($row) $target = (int)$row['id'];
}
if ($target <= 0) json_error('ユーザーが指定されていません');
if ($target === (int)$u['id']) json_error('自分自身はフォローできません');

// 対象ユーザーの存在確認
$st = db()->prepare('SELECT id FROM users WHERE id = ?');
$st->execute([$target]);
if (!$st->fetch()) json_error('対象のユーザーが見つかりません', 404);

// すでにフォローしているか
$st = db()->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?');
$st->execute([$u['id'], $target]);
$already = (bool)$st->fetch();

if ($already) {
  $st = db()->prepare('DELETE FROM follows WHERE follower_id = ? AND followee_id = ?');
  $st->execute([$u['id'], $target]);
  $following = 0;
} else {
  $st = db()->prepare('INSERT IGNORE INTO follows (follower_id, followee_id) VALUES (?, ?)');
  $st->execute([$u['id'], $target]);
  $following = 1;
  // フォローされた相手に通知（解除時は通知しない）
  notify($target, (int)$u['id'], 'follow', null);
}

// 対象の最新フォロワー数
$st = db()->prepare('SELECT COUNT(*) AS c FROM follows WHERE followee_id = ?');
$st->execute([$target]);
$followerCount = (int)($st->fetch()['c'] ?? 0);

json_out(['ok' => true, 'following' => $following, 'followerCount' => $followerCount]);
