<?php
// 指定ユーザーの「公開プロフィール」＋「公開記録」を返す（user.html 用）
// 閲覧は誰でも可（ログイン不要）。is_public = 1 の記録だけを公開する。
// メールアドレスなどの非公開情報は返さない。
require __DIR__ . '/lib.php';

$user_id = (int)($_GET['user_id'] ?? 0);
if ($user_id <= 0) json_error('ユーザーが指定されていません');

// 公開プロフィール（nickname / city / x_handle / avatar / bio のみ。emailは返さない）
$st = db()->prepare('SELECT id, nickname, city, x_handle, avatar, bio FROM users WHERE id = ?');
$st->execute([$user_id]);
$u = $st->fetch();
if (!$u) json_error('ユーザーが見つかりません', 404);

// 公開記録のみ（古い順＝スタンプを押した順）
$st = db()->prepare(
  'SELECT id, shop_id, menus, comment, visit_date, photo_count, created_at,
          (SELECT COUNT(*) FROM likes lk WHERE lk.log_id = logs.id) AS like_count
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
    'nickname'  => $u['nickname'],
    'city'      => $u['city'],
    'xHandle'   => $u['x_handle'],
    'avatarUrl' => avatar_url($u['avatar'] ?? null),
    'bio'       => $u['bio'],
  ],
  'logs' => $logs,
]);
