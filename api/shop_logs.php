<?php
// ある店の「みんなの公開投稿」＋（ログイン中なら）自分の訪問数・最終訪問・評価
// 閲覧は誰でも可（ログイン不要）。shop_detail 用。
require __DIR__ . '/lib.php';

$shop_id = (int)($_GET['shop_id'] ?? 0);
if ($shop_id <= 0) json_error('店舗が指定されていません');

// 公開投稿（新しい順・最大100件）
$st = db()->prepare(
  'SELECT u.nickname, u.x_handle, l.menus, l.comment, l.visit_date, l.photo_count, l.created_at
   FROM logs l JOIN users u ON u.id = l.user_id
   WHERE l.shop_id = ? AND l.is_public = 1
   ORDER BY l.id DESC LIMIT 100'
);
$st->execute([$shop_id]);
$posts = [];
foreach ($st as $r) {
  $posts[] = [
    'nickname'  => $r['nickname'],
    'xHandle'   => $r['x_handle'],   // Xユーザー名（@なし。nullなら未設定）
    'menus'     => ($r['menus'] !== null && $r['menus'] !== '') ? explode('・', $r['menus']) : [],
    'comment'   => $r['comment'] ?? '',
    'visitDate' => $r['visit_date'],
    'photoCount'=> (int)$r['photo_count'],
    'savedAt'   => $r['created_at'],
  ];
}

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
  $my = [
    'count'     => (int)($row['c'] ?? 0),
    'lastVisit' => $row['last'] ?? null,
    'rating'    => $rt ? (int)$rt['score'] : 0,
  ];
}

json_out(['ok' => true, 'posts' => $posts, 'my' => $my]);
