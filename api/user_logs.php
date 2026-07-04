<?php
// 指定ユーザーの「公開プロフィール」＋「公開記録」を返す（user.html 用）
// 閲覧は誰でも可（ログイン不要）。is_public = 1 の記録だけを公開する。
// メールアドレスなどの非公開情報は返さない。
require __DIR__ . '/lib.php';

// プロフィールは username（?u=）で特定。互換のため user_id（?user_id=）も受ける。
$uname   = normalize_username((string)($_GET['u'] ?? ''));
$user_id = (int)($_GET['user_id'] ?? 0);

// 公開プロフィール（username / nickname / city / x_handle / avatar / bio のみ。emailは返さない）
if ($uname !== '') {
  $st = db()->prepare('SELECT id, username, nickname, city, x_handle, avatar, bio FROM users WHERE username = ?');
  $st->execute([$uname]);
} elseif ($user_id > 0) {
  $st = db()->prepare('SELECT id, username, nickname, city, x_handle, avatar, bio FROM users WHERE id = ?');
  $st->execute([$user_id]);
} else {
  json_error('ユーザーが指定されていません');
}
$u = $st->fetch();
if (!$u) json_error('ユーザーが見つかりません', 404);
$user_id = (int)$u['id'];   // 以降の記録・推し店クエリで使う

// フォロー関係（フォロワー数・フォロー中数・閲覧者がフォロー中か・本人か）
$viewer   = current_user();
$viewerId = $viewer ? (int)$viewer['id'] : 0;
$st = db()->prepare('SELECT COUNT(*) AS c FROM follows WHERE followee_id = ?');
$st->execute([$user_id]);
$followerCount = (int)($st->fetch()['c'] ?? 0);
$st = db()->prepare('SELECT COUNT(*) AS c FROM follows WHERE follower_id = ?');
$st->execute([$user_id]);
$followingCount = (int)($st->fetch()['c'] ?? 0);
$isSelf = ($viewerId > 0 && $viewerId === $user_id);
$isFollowing = false;
if ($viewerId > 0 && !$isSelf) {
  $st = db()->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?');
  $st->execute([$viewerId, $user_id]);
  $isFollowing = (bool)$st->fetch();
}

// 公開記録のみ（古い順＝スタンプを押した順）
$st = db()->prepare(
  'SELECT id, shop_id, menus, comment, visit_date, photo_count, created_at,
          (SELECT COUNT(*) FROM likes lk WHERE lk.log_id = logs.id) AS like_count,
          (SELECT COUNT(*) FROM comments cm WHERE cm.log_id = logs.id) AS comment_count
   FROM logs WHERE user_id = ? AND is_public = 1 ORDER BY id'
);
$st->execute([$user_id]);

$logs = [];
foreach ($st as $r) {
  $logs[] = [
    'id'        => (int)$r['id'],
    'shopId'    => (int)$r['shop_id'],
    'menus'     => ($r['menus'] !== null && $r['menus'] !== '') ? explode('・', $r['menus']) : [],
    'comment'   => $r['comment'] ?? '',
    'visitDate' => $r['visit_date'],
    'photoCount'=> (int)$r['photo_count'],
    'savedAt'   => $r['created_at'],
    'photoUrl'  => record_photo_url((int)$r['id']),
    'likeCount' => (int)$r['like_count'],
    'commentCount' => (int)$r['comment_count'],
  ];
}

// 推しのうどん屋（店ID配列・並び順）
$st = db()->prepare('SELECT shop_id FROM favorite_shops WHERE user_id = ? ORDER BY sort_order, shop_id');
$st->execute([$user_id]);
$favorites = [];
foreach ($st as $r) { $favorites[] = (int)$r['shop_id']; }

json_out([
  'ok'        => true,
  'favorites' => $favorites,
  'user' => [
    'id'        => (int)$u['id'],
    'username'  => $u['username'],
    'nickname'  => $u['nickname'],
    'city'      => $u['city'],
    'xHandle'   => $u['x_handle'],
    'avatarUrl' => avatar_url($u['avatar'] ?? null),
    'bio'       => $u['bio'],
    'followerCount'  => $followerCount,
    'followingCount' => $followingCount,
    'isFollowing'    => $isFollowing,
    'isSelf'         => $isSelf,
  ],
  'logs' => $logs,
]);
