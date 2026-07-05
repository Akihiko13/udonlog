<?php
// ログイン中ユーザーの通知一覧（新しい順・最大50件）＋未読数を返す。
// 返り値: { ok, notifications: [ {id,type,actor{...},shopId,createdAt,isRead} ], unread }
require __DIR__ . '/lib.php';
$u = require_login();

$st = db()->prepare(
  'SELECT n.id, n.actor_id, n.type, n.log_id, n.is_read, n.created_at,
          a.username, a.nickname, a.avatar,
          l.shop_id
   FROM notifications n
   JOIN users a ON a.id = n.actor_id
   LEFT JOIN logs l ON l.id = n.log_id
   WHERE n.user_id = ?
   ORDER BY n.id DESC
   LIMIT 50'
);
$st->execute([$u['id']]);

$items = [];
foreach ($st as $r) {
  $items[] = [
    'id'        => (int)$r['id'],
    'type'      => $r['type'],                         // comment | follow | like
    'actor'     => [
      'username'  => $r['username'],
      'nickname'  => $r['nickname'],
      'avatarUrl' => avatar_url($r['avatar'] ?? null),
    ],
    'shopId'    => $r['shop_id'] !== null ? (int)$r['shop_id'] : null,  // comment/like の投稿の店（遷移先用）
    'createdAt' => $r['created_at'],
    'isRead'    => ((int)$r['is_read']) === 1,
  ];
}

json_out(['ok' => true, 'notifications' => $items, 'unread' => unread_notif_count((int)$u['id'])]);
