<?php
// 記録を1件追加（ログイン必須）
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in      = body();
$shop_id = (int)($in['shop_id'] ?? 0);
if ($shop_id <= 0) json_error('店舗が指定されていません');

// メニュー（配列なら「・」で連結して保存）。食べたうどんは最低1つ必須
// 「・」はメニュー間の区切り文字なので、各メニュー内の「・」（自由入力に混ざると
// 人気メニュー集計で誤って分割される）は全角スペースに置換して無害化する。
$menus = $in['menus'] ?? '';
if (is_array($menus)) {
  $menus = implode('・', array_filter(array_map(function ($m) {
    return str_replace('・', '　', trim((string)$m));
  }, $menus), 'strlen'));
}
$menus = trim((string)$menus);
if ($menus === '') json_error('食べたうどんを選んでください');
if (mb_strlen($menus) > 255) $menus = mb_substr($menus, 0, 255);

$comment = trim((string)($in['comment'] ?? ''));
if (mb_strlen($comment) > 140) $comment = mb_substr($comment, 0, 140);   // 感想は140文字まで

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

// その日の何杯目か（この記録を含む）。訪問日があればその日で、無ければ本日(created_at)で数える。
// 完了演出で「2杯目以降は満腹うどまる」に出し分けるのに使う。
if ($visit !== null) {
  $cst = db()->prepare('SELECT COUNT(*) FROM logs WHERE user_id = ? AND visit_date = ?');
  $cst->execute([$u['id'], $visit]);
} else {
  $cst = db()->prepare('SELECT COUNT(*) FROM logs WHERE user_id = ? AND DATE(created_at) = CURDATE()');
  $cst->execute([$u['id']]);
}
$dayCount = (int)$cst->fetchColumn();

json_out([
  'ok'  => true,
  'dayCount' => $dayCount,   // その日の通算杯数（この記録を含む）
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
