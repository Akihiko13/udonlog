<?php
// 自分の記録を編集（ログイン必須・本人の記録のみ）。お店は変更しない。
// メニュー・コメント・訪問日・公開設定を更新する。写真は log_photo.php / log_photo_delete.php で扱う。
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in = body();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) json_error('記録が指定されていません');

// 自分の記録か確認
$st = db()->prepare('SELECT id FROM logs WHERE id = ? AND user_id = ?');
$st->execute([$id, $u['id']]);
if (!$st->fetch()) json_error('記録が見つかりません', 404);

// メニュー（最低1つ必須・log_add.php と同じ扱い）
// 各メニュー内の「・」は区切りと衝突するため全角スペースに置換して無害化する。
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
if (mb_strlen($comment) > 140) $comment = mb_substr($comment, 0, 140);

$visit = trim((string)($in['visit_date'] ?? ''));
$visit = ($visit !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit)) ? $visit : null;

$isPub = array_key_exists('is_public', $in) ? (int)!!$in['is_public'] : 1;

$st = db()->prepare(
  'UPDATE logs SET menus = ?, comment = ?, visit_date = ?, is_public = ? WHERE id = ? AND user_id = ?'
);
$st->execute([($menus !== '' ? $menus : null), ($comment !== '' ? $comment : null), $visit, $isPub, $id, $u['id']]);

json_out(['ok' => true, 'log' => ['id' => $id]]);
