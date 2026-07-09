<?php
// 全会員の公開投稿を新着順（id DESC）で返す「新着投稿フィード」用API。
// 閲覧は誰でも可（ログイン不要）。ログイン中なら自分の「いいね」有無も返す。
// ページングは before_id 方式：?before_id=123 を渡すと、そのidより小さい投稿を返す。
require __DIR__ . '/lib.php';

const FEED_LIMIT = 30;   // 1ページあたりの件数

// 閲覧者（ログイン中なら自分が「いいね」したか判定するのに使う）
$viewer = current_user();
$viewerId = $viewer ? (int)$viewer['id'] : 0;

// before_id より小さいidだけを対象（未指定なら最新から）
$beforeId = (int)($_GET['before_id'] ?? 0);

// フォロー中モード（?following=1）：自分がフォローしている人の投稿だけに絞る。
// 未ログインでは対象が無いので空を返す。
$following = ((int)($_GET['following'] ?? 0) === 1);
if ($following && $viewerId === 0) {
  json_out(['ok' => true, 'posts' => [], 'hasMore' => false, 'nextBefore' => null]);
}

// 公開投稿を新しい順に FEED_LIMIT+1 件取得（+1件は「まだ続きがあるか」の判定用）
$where = 'l.is_public = 1';
// SELECT内のサブクエリ用（WHEREより先に束縛される）。順番＝ liked_by_me → following_by_me。
$params = [$viewerId, $viewerId];
if ($following) {
  $where .= ' AND l.user_id IN (SELECT followee_id FROM follows WHERE follower_id = ?)';
  $params[] = $viewerId;
}
if ($beforeId > 0) {
  $where .= ' AND l.id < ?';
  $params[] = $beforeId;
}
$sql =
  'SELECT l.id AS log_id, u.id AS user_id, u.username, u.nickname, u.x_handle, u.avatar,
          l.shop_id, l.menus, l.comment, l.visit_date, l.photo_count, l.created_at,
          (SELECT COUNT(*) FROM likes lk WHERE lk.log_id = l.id) AS like_count,
          (SELECT COUNT(*) FROM comments cm WHERE cm.log_id = l.id) AS comment_count,
          (SELECT COUNT(*) FROM likes lk2 WHERE lk2.log_id = l.id AND lk2.user_id = ?) AS liked_by_me,
          (SELECT COUNT(*) FROM follows f WHERE f.follower_id = ? AND f.followee_id = l.user_id) AS following_by_me
   FROM logs l JOIN users u ON u.id = l.user_id
   WHERE ' . $where . '
   ORDER BY l.id DESC LIMIT ' . (FEED_LIMIT + 1);
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// FEED_LIMIT+1 件取れたら、まだ続きがある。余分な1件は返さない。
$hasMore = count($rows) > FEED_LIMIT;
if ($hasMore) array_pop($rows);

$posts = [];
foreach ($rows as $r) {
  $posts[] = [
    'logId'     => (int)$r['log_id'],    // いいねの対象
    'userId'    => (int)$r['user_id'],   // 投稿者のプロフィールページ用
    'username'  => $r['username'],       // プロフィールURL /username 用
    'nickname'  => $r['nickname'],
    'xHandle'   => $r['x_handle'],       // Xユーザー名（@なし。nullなら未設定）
    'avatarUrl' => avatar_url($r['avatar'] ?? null),
    'shopId'    => (int)$r['shop_id'],   // 店名・きれいURLはフロントの SHOPS から引く
    'photoUrl'  => record_photo_url((int)$r['log_id']),
    'menus'     => ($r['menus'] !== null && $r['menus'] !== '') ? explode('・', $r['menus']) : [],
    'comment'   => $r['comment'] ?? '',
    'visitDate' => $r['visit_date'],
    'photoCount'=> (int)$r['photo_count'],
    'savedAt'   => $r['created_at'],
    'likeCount' => (int)$r['like_count'],
    'commentCount' => (int)$r['comment_count'],
    'liked'     => ((int)$r['liked_by_me']) > 0 ? 1 : 0,
    // 投稿ヘッダーのフォローボタン用。isSelf=自分の投稿 / isFollowing=閲覧者がこの人をフォロー中
    'isSelf'      => ($viewerId > 0 && (int)$r['user_id'] === $viewerId) ? 1 : 0,
    'isFollowing' => ((int)$r['following_by_me']) > 0 ? 1 : 0,
  ];
}

// 次ページ取得用のカーソル（最後の投稿のid）。続きが無ければ null。
$nextBefore = ($hasMore && $posts) ? $posts[count($posts) - 1]['logId'] : null;

json_out(['ok' => true, 'posts' => $posts, 'hasMore' => $hasMore, 'nextBefore' => $nextBefore]);
