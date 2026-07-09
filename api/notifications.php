<?php
// ログイン中ユーザーの通知一覧（新しい順）＋未読数を返す。
// ページングは before_id 方式：?before_id=123 でそのidより古い通知を NOTIF_LIMIT 件返す。
// 返り値: { ok, notifications:[ {id,type,actor{...},shopId,createdAt,isRead} ], hasMore, nextBefore, unread }
require __DIR__ . '/lib.php';
$u = require_login();

const NOTIF_LIMIT = 30;   // 1ページあたりの件数

// before_id より小さいidだけを対象（未指定なら最新から）
$beforeId = (int)($_GET['before_id'] ?? 0);
$where = 'n.user_id = ?';
$params = [$u['id']];
if ($beforeId > 0) {
  $where .= ' AND n.id < ?';
  $params[] = $beforeId;
}

// NOTIF_LIMIT+1 件取得（+1件は「まだ続きがあるか」の判定用）
$st = db()->prepare(
  'SELECT n.id, n.actor_id, n.type, n.log_id, n.is_read, n.created_at,
          a.username, a.nickname, a.avatar,
          l.shop_id
   FROM notifications n
   JOIN users a ON a.id = n.actor_id
   LEFT JOIN logs l ON l.id = n.log_id
   WHERE ' . $where . '
   ORDER BY n.id DESC
   LIMIT ' . (NOTIF_LIMIT + 1)
);
$st->execute($params);
$rows = $st->fetchAll();

// NOTIF_LIMIT+1 件取れたら、まだ続きがある。余分な1件は返さない。
$hasMore = count($rows) > NOTIF_LIMIT;
if ($hasMore) array_pop($rows);

$items = [];
foreach ($rows as $r) {
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

// 次ページ取得用カーソル（最後の通知のid）。続きが無ければ null。
$nextBefore = ($hasMore && $items) ? $items[count($items) - 1]['id'] : null;

json_out([
  'ok'            => true,
  'notifications' => $items,
  'hasMore'       => $hasMore,
  'nextBefore'    => $nextBefore,
  'unread'        => unread_notif_count((int)$u['id']),
]);
