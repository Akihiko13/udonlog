<?php
// ログイン中ユーザーの全記録＋おすすめ度を返す（mypage / shops 用）
require __DIR__ . '/lib.php';
$u = require_login();

$st = db()->prepare(
  'SELECT id, shop_id, menus, comment, visit_date, photo_count, is_public, created_at,
          (SELECT COUNT(*) FROM likes lk WHERE lk.log_id = logs.id) AS like_count,
          (SELECT COUNT(*) FROM comments cm WHERE cm.log_id = logs.id) AS comment_count,
          (SELECT COUNT(*) FROM likes lk2 WHERE lk2.log_id = logs.id AND lk2.user_id = ?) AS liked_by_me
   FROM logs WHERE user_id = ? ORDER BY id'
);
$st->execute([$u['id'], $u['id']]);

$logs = [];
foreach ($st as $r) {
  $logs[] = [
    'id'        => (int)$r['id'],
    'shopId'    => (int)$r['shop_id'],
    'menus'     => ($r['menus'] !== null && $r['menus'] !== '') ? explode('・', $r['menus']) : [],
    'comment'   => $r['comment'] ?? '',
    'visitDate' => $r['visit_date'],
    'photoCount'=> (int)$r['photo_count'],
    'isPublic'  => (int)$r['is_public'],
    'savedAt'   => $r['created_at'],
    'photoUrl'  => record_photo_url((int)$r['id']),
    'likeCount' => (int)$r['like_count'],
    'commentCount' => (int)$r['comment_count'],
    'liked'     => ((int)$r['liked_by_me']) > 0 ? 1 : 0,
  ];
}

// おすすめ度（shopId => score）
$st = db()->prepare('SELECT shop_id, score FROM ratings WHERE user_id = ?');
$st->execute([$u['id']]);
$ratings = [];
foreach ($st as $r) { $ratings[(string)(int)$r['shop_id']] = (int)$r['score']; }

// フォロワー数・フォロー中数（マイページの一覧への入口用）
$st = db()->prepare('SELECT COUNT(*) AS c FROM follows WHERE followee_id = ?');
$st->execute([$u['id']]);
$followerCount = (int)($st->fetch()['c'] ?? 0);
$st = db()->prepare('SELECT COUNT(*) AS c FROM follows WHERE follower_id = ?');
$st->execute([$u['id']]);
$followingCount = (int)($st->fetch()['c'] ?? 0);

json_out([
  'ok' => true, 'logs' => $logs, 'ratings' => $ratings,
  'followerCount' => $followerCount, 'followingCount' => $followingCount,
]);
