<?php
// 香川うどんランキング（ログイン不要）。
// ?type=rating  … おすすめ度の平均が高い順（評価 MIN_RATINGS 件以上の店のみ）
// ?type=records … 公開記録の多い順（＝よく記録されている＝人気）
// 店名・エリア・スラッグはフロントの SHOPS（shops-data.js）から引くため、
// ここでは shop_id ＋ 集計値だけを返す軽量設計。閉店店の除外もフロント側で行う。
// 返り値: { ok, type, items: [...] }
require __DIR__ . '/lib.php';

// おすすめ度ランキングに載せる最低評価件数。
// ※データが少ないうちは 1。評価が貯まったら 3 に上げる（1〜2件の偏りを防ぐ）。
const MIN_RATINGS = 1;

// 返す最大件数。閉店店をフロントで除くので、少し多めに返しておく。
const RANK_LIMIT = 100;

$type = (string)($_GET['type'] ?? 'rating');

if ($type === 'records') {
  // 公開記録の多い順（同数なら記録した人数が多い順）
  $st = db()->query(
    'SELECT shop_id,
            COUNT(*)               AS records,
            COUNT(DISTINCT user_id) AS users
     FROM logs
     WHERE is_public = 1
     GROUP BY shop_id
     ORDER BY records DESC, users DESC
     LIMIT ' . RANK_LIMIT
  );
  $items = [];
  foreach ($st as $r) {
    $items[] = [
      'shopId'  => (int)$r['shop_id'],
      'records' => (int)$r['records'],
      'users'   => (int)$r['users'],
    ];
  }
  json_out(['ok' => true, 'type' => 'records', 'items' => $items]);
}

// 既定：おすすめ度ランキング（平均が高い順・同点は評価数が多い順）
$st = db()->prepare(
  'SELECT shop_id,
          ROUND(AVG(score), 1) AS avg,
          COUNT(*)             AS count
   FROM ratings
   GROUP BY shop_id
   HAVING count >= ?
   ORDER BY avg DESC, count DESC
   LIMIT ' . RANK_LIMIT
);
$st->execute([MIN_RATINGS]);
$items = [];
foreach ($st as $r) {
  $items[] = [
    'shopId' => (int)$r['shop_id'],
    'avg'    => (float)$r['avg'],
    'count'  => (int)$r['count'],
  ];
}
json_out(['ok' => true, 'type' => 'rating', 'items' => $items]);
