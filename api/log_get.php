<?php
// 自分の記録を1件取得（編集画面の初期値用・ログイン必須）
require __DIR__ . '/lib.php';
$u = require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) json_error('記録が指定されていません');

$st = db()->prepare(
  'SELECT id, shop_id, menus, comment, visit_date, photo_count, is_public
   FROM logs WHERE id = ? AND user_id = ?'
);
$st->execute([$id, $u['id']]);
$r = $st->fetch();
if (!$r) json_error('記録が見つかりません', 404);

json_out(['ok' => true, 'log' => [
  'id'        => (int)$r['id'],
  'shopId'    => (int)$r['shop_id'],
  'menus'     => ($r['menus'] !== null && $r['menus'] !== '') ? explode('・', $r['menus']) : [],
  'comment'   => $r['comment'] ?? '',
  'visitDate' => $r['visit_date'],
  'isPublic'  => (int)$r['is_public'],
  'photoUrl'  => record_photo_url((int)$r['id']),
]]);
