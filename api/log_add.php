<?php
// 記録を1件追加（ログイン必須）
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in      = body();
$shop_id = (int)($in['shop_id'] ?? 0);
if ($shop_id <= 0) json_error('店舗が指定されていません');

// メニュー（配列なら「・」で連結して保存）
$menus = $in['menus'] ?? '';
if (is_array($menus)) $menus = implode('・', array_map('strval', $menus));
$menus = trim((string)$menus);
if (mb_strlen($menus) > 255) $menus = mb_substr($menus, 0, 255);

$comment = trim((string)($in['comment'] ?? ''));

// 訪問日（YYYY-MM-DD のみ受理。それ以外はNULL）
$visit = trim((string)($in['visit_date'] ?? ''));
$visit = ($visit !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit)) ? $visit : null;

$photo = (int)($in['photo_count'] ?? 0);
if ($photo < 0) $photo = 0;
if ($photo > 10) $photo = 10;

// 公開フラグ（指定が無ければ公開）
$isPub = array_key_exists('is_public', $in) ? (int)!!$in['is_public'] : 1;

$st = db()->prepare(
  'INSERT INTO logs (user_id, shop_id, menus, comment, visit_date, photo_count, is_public)
   VALUES (?,?,?,?,?,?,?)'
);
$st->execute([$u['id'], $shop_id, ($menus !== '' ? $menus : null), ($comment !== '' ? $comment : null), $visit, $photo, $isPub]);
$id = (int)db()->lastInsertId();

json_out([
  'ok'  => true,
  'log' => [
    'id'        => $id,
    'shopId'    => $shop_id,
    'menus'     => ($menus !== '' ? explode('・', $menus) : []),
    'comment'   => $comment,
    'visitDate' => $visit,
    'photoCount'=> $photo,
    'isPublic'  => $isPub,
    'savedAt'   => date('c'),
  ],
]);
