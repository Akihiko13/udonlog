<?php
// 推しのうどん屋を保存（ログイン必須）。送られた店ID配列で全置き換え（最大3軒）。
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

const FAV_MAX = 3;

$in  = body();
$ids = $in['shop_ids'] ?? [];
if (!is_array($ids)) $ids = [];

// 整数化・重複除去・正の値のみ・最大件数まで
$clean = [];
foreach ($ids as $id) {
  $id = (int)$id;
  if ($id > 0 && !in_array($id, $clean, true)) $clean[] = $id;
}
$clean = array_slice($clean, 0, FAV_MAX);

$pdo = db();
$pdo->beginTransaction();
try {
  $st = $pdo->prepare('DELETE FROM favorite_shops WHERE user_id = ?');
  $st->execute([$u['id']]);
  if ($clean) {
    $ins = $pdo->prepare('INSERT INTO favorite_shops (user_id, shop_id, sort_order) VALUES (?,?,?)');
    foreach ($clean as $i => $sid) { $ins->execute([$u['id'], $sid, $i]); }
  }
  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  json_error('保存に失敗しました。時間をおいて再度お試しください。', 500);
}

json_out(['ok' => true, 'shopIds' => $clean]);
