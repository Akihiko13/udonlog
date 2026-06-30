<?php
// 推しのうどん屋の店ID配列を返す。?user_id= 指定でその会員、無指定ならログイン中の自分。
require __DIR__ . '/lib.php';

$uid = (int)($_GET['user_id'] ?? 0);
if ($uid <= 0) {
  $me = current_user();
  if (!$me) json_error('ユーザーが指定されていません');
  $uid = $me['id'];
}

$st = db()->prepare('SELECT shop_id FROM favorite_shops WHERE user_id = ? ORDER BY sort_order, shop_id');
$st->execute([$uid]);
$ids = [];
foreach ($st as $r) { $ids[] = (int)$r['shop_id']; }

json_out(['ok' => true, 'shopIds' => $ids]);
