<?php
// 指定ユーザーの「フォロワー」または「フォロー中」の会員一覧を返す。
// 閲覧は誰でも可（ログイン不要）。ログイン中なら各会員を自分がフォロー中かも返す。
//   ?u=<username>（または ?user_id=）で対象ユーザー、?type=followers|following
// 返り値: { ok, type, owner:{username,nickname}, followerCount, followingCount,
//           users:[ {username,nickname,avatarUrl,isFollowing,isSelf} ] }
require __DIR__ . '/lib.php';

$type = ($_GET['type'] ?? 'followers') === 'following' ? 'following' : 'followers';

// 対象ユーザーを特定
$uname   = normalize_username((string)($_GET['u'] ?? ''));
$user_id = (int)($_GET['user_id'] ?? 0);
if ($uname !== '') {
  $st = db()->prepare('SELECT id, username, nickname FROM users WHERE username = ?');
  $st->execute([$uname]);
} elseif ($user_id > 0) {
  $st = db()->prepare('SELECT id, username, nickname FROM users WHERE id = ?');
  $st->execute([$user_id]);
} else {
  json_error('ユーザーが指定されていません');
}
$owner = $st->fetch();
if (!$owner) json_error('ユーザーが見つかりません', 404);
$ownerId = (int)$owner['id'];

$viewer   = current_user();
$viewerId = $viewer ? (int)$viewer['id'] : 0;

// フォロワー：ownerをフォローしている人（f.follower_id）
// フォロー中：ownerがフォローしている人（f.followee_id）
if ($type === 'following') {
  $joinCol  = 'f.followee_id';   // 一覧に出す相手
  $whereCol = 'f.follower_id';   // = owner
} else {
  $joinCol  = 'f.follower_id';
  $whereCol = 'f.followee_id';
}

$sql =
  'SELECT u.id, u.username, u.nickname, u.avatar,
          (SELECT 1 FROM follows vf WHERE vf.follower_id = ? AND vf.followee_id = u.id) AS viewer_follows
   FROM follows f JOIN users u ON u.id = ' . $joinCol . '
   WHERE ' . $whereCol . ' = ?
   ORDER BY f.created_at DESC, u.id DESC
   LIMIT 200';
$st = db()->prepare($sql);
$st->execute([$viewerId, $ownerId]);

$users = [];
foreach ($st as $r) {
  $uid = (int)$r['id'];
  $users[] = [
    'username'    => $r['username'],
    'nickname'    => $r['nickname'],
    'avatarUrl'   => avatar_url($r['avatar'] ?? null),
    'isFollowing' => ((int)($r['viewer_follows'] ?? 0)) > 0,
    'isSelf'      => ($viewerId > 0 && $uid === $viewerId),
  ];
}

// タブ見出し用の件数
$st = db()->prepare('SELECT COUNT(*) AS c FROM follows WHERE followee_id = ?');
$st->execute([$ownerId]);
$followerCount = (int)($st->fetch()['c'] ?? 0);
$st = db()->prepare('SELECT COUNT(*) AS c FROM follows WHERE follower_id = ?');
$st->execute([$ownerId]);
$followingCount = (int)($st->fetch()['c'] ?? 0);

json_out([
  'ok'   => true,
  'type' => $type,
  'owner' => ['username' => $owner['username'], 'nickname' => $owner['nickname']],
  'followerCount'  => $followerCount,
  'followingCount' => $followingCount,
  'users' => $users,
]);
