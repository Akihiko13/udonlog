<?php
// お店のおすすめ度を保存（ログイン必須・1ユーザー1店で上書き）
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in      = body();
$shop_id = (int)($in['shop_id'] ?? 0);
$score   = (int)($in['score'] ?? 0);
if ($shop_id <= 0)              json_error('店舗が指定されていません');
if ($score < 1 || $score > 5)   json_error('評価は1〜5で指定してください');

$st = db()->prepare(
  'INSERT INTO ratings (user_id, shop_id, score) VALUES (?,?,?)
   ON DUPLICATE KEY UPDATE score = VALUES(score)'
);
$st->execute([$u['id'], $shop_id, $score]);

json_out(['ok' => true, 'shopId' => $shop_id, 'score' => $score]);
