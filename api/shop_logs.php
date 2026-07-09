<?php
// ある店の「みんなの公開投稿」＋（ログイン中なら）自分の訪問数・最終訪問・評価
// 閲覧は誰でも可（ログイン不要）。shop_detail 用。
require __DIR__ . '/lib.php';

$shop_id = (int)($_GET['shop_id'] ?? 0);
if ($shop_id <= 0) json_error('店舗が指定されていません');

// 閲覧者（ログイン中なら自分が「いいね」したか判定するのに使う）
$viewer = current_user();
$viewerId = $viewer ? (int)$viewer['id'] : 0;

// 公開投稿（新しい順・最大100件）＋いいね数・自分のいいね有無
$st = db()->prepare(
  'SELECT l.id AS log_id, u.id AS user_id, u.username, u.nickname, u.x_handle, u.avatar,
          l.menus, l.comment, l.visit_date, l.photo_count, l.created_at,
          (SELECT COUNT(*) FROM likes lk WHERE lk.log_id = l.id) AS like_count,
          (SELECT COUNT(*) FROM comments cm WHERE cm.log_id = l.id) AS comment_count,
          (SELECT COUNT(*) FROM likes lk2 WHERE lk2.log_id = l.id AND lk2.user_id = ?) AS liked_by_me,
          (SELECT COUNT(*) FROM follows f WHERE f.follower_id = ? AND f.followee_id = l.user_id) AS following_by_me
   FROM logs l JOIN users u ON u.id = l.user_id
   WHERE l.shop_id = ? AND l.is_public = 1
   ORDER BY l.id DESC LIMIT 100'
);
// プレースホルダ順＝ liked_by_me, following_by_me, WHERE shop_id
$st->execute([$viewerId, $viewerId, $shop_id]);
$posts = [];
foreach ($st as $r) {
  $posts[] = [
    'logId'     => (int)$r['log_id'],    // いいねの対象
    'userId'    => (int)$r['user_id'],   // 投稿者のプロフィールページ用
    'username'  => $r['username'],       // プロフィールURL /username 用
    'nickname'  => $r['nickname'],
    'xHandle'   => $r['x_handle'],   // Xユーザー名（@なし。nullなら未設定）
    'avatarUrl' => avatar_url($r['avatar'] ?? null),
    'photoUrl'  => record_photo_url((int)$r['log_id']),
    'menus'     => ($r['menus'] !== null && $r['menus'] !== '') ? explode('・', $r['menus']) : [],
    'comment'   => $r['comment'] ?? '',
    'visitDate' => $r['visit_date'],
    'photoCount'=> (int)$r['photo_count'],
    'savedAt'   => $r['created_at'],
    'likeCount' => (int)$r['like_count'],
    'commentCount' => (int)$r['comment_count'],
    'liked'     => ((int)$r['liked_by_me']) > 0 ? 1 : 0,
    // 投稿ヘッダーのフォローボタン用。isSelf=自分の投稿 / isFollowing=閲覧者がフォロー中
    'isSelf'      => ($viewerId > 0 && (int)$r['user_id'] === $viewerId) ? 1 : 0,
    'isFollowing' => ((int)$r['following_by_me']) > 0 ? 1 : 0,
  ];
}

// この店のおすすめ度の集計（全会員・スコア分布／件数／平均）
$st = db()->prepare('SELECT score, COUNT(*) AS c FROM ratings WHERE shop_id = ? GROUP BY score');
$st->execute([$shop_id]);
$dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($st as $r) {
  $s = (int)$r['score'];
  if ($s >= 1 && $s <= 5) $dist[$s] = (int)$r['c'];
}
$ratingTotal = array_sum($dist);
$ratingSum = 0;
foreach ($dist as $s => $c) $ratingSum += $s * $c;
$ratingAvg = $ratingTotal > 0 ? round($ratingSum / $ratingTotal, 1) : 0;
$rating = ['dist' => $dist, 'total' => $ratingTotal, 'avg' => $ratingAvg];

// 人気メニュー（全記録のメニューを集計。記録数が多い順。1件も無ければ空配列）
$st = db()->prepare("SELECT menus FROM logs WHERE shop_id = ? AND menus IS NOT NULL AND menus <> ''");
$st->execute([$shop_id]);
$menuCount = [];
foreach ($st as $r) {
  foreach (explode('・', $r['menus']) as $m) {
    $m = trim($m);
    if ($m === '') continue;
    $menuCount[$m] = ($menuCount[$m] ?? 0) + 1;
  }
}
arsort($menuCount);
$popularMenus = [];
foreach ($menuCount as $name => $c) { $popularMenus[] = ['name' => $name, 'count' => $c]; }

// ログイン中なら自分の集計
$my = null;
$me = current_user();
if ($me) {
  $st = db()->prepare('SELECT COUNT(*) AS c, MAX(visit_date) AS last FROM logs WHERE user_id = ? AND shop_id = ?');
  $st->execute([$me['id'], $shop_id]);
  $row = $st->fetch();
  $st = db()->prepare('SELECT score FROM ratings WHERE user_id = ? AND shop_id = ?');
  $st->execute([$me['id'], $shop_id]);
  $rt = $st->fetch();
  // 常連カードのスタンプに表示する記録日（記録した順＝古い順）
  $st = db()->prepare('SELECT COALESCE(visit_date, DATE(created_at)) AS d FROM logs WHERE user_id = ? AND shop_id = ? ORDER BY id');
  $st->execute([$me['id'], $shop_id]);
  $dates = [];
  foreach ($st as $r) { $dates[] = $r['d']; }
  $my = [
    'count'     => (int)($row['c'] ?? 0),
    'lastVisit' => $row['last'] ?? null,
    'rating'    => $rt ? (int)$rt['score'] : 0,
    'dates'     => $dates,
  ];
}

json_out(['ok' => true, 'posts' => $posts, 'my' => $my, 'rating' => $rating, 'popularMenus' => $popularMenus]);
