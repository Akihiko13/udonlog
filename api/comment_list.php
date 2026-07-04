<?php
// ある投稿(log)へのコメント一覧を返す。閲覧は誰でも可（ログイン不要）。
// 非公開投稿のコメントは、その投稿の持ち主だけが見られる。
// 返り値: { ok, comments: [ {id,userId,username,nickname,avatarUrl,body,createdAt,canDelete} ] }
require __DIR__ . '/lib.php';

$log_id = (int)($_GET['log_id'] ?? 0);
if ($log_id <= 0) json_error('投稿が指定されていません');

$viewer   = current_user();
$viewerId = $viewer ? (int)$viewer['id'] : 0;

// 対象の投稿を取得（公開か、自分の投稿なら閲覧可）
$st = db()->prepare('SELECT id, user_id, is_public FROM logs WHERE id = ?');
$st->execute([$log_id]);
$log = $st->fetch();
if (!$log) json_error('対象の投稿が見つかりません', 404);
$ownerId = (int)$log['user_id'];
if ((int)$log['is_public'] !== 1 && $viewerId !== $ownerId) {
  json_error('この投稿のコメントは表示できません', 403);
}

$st = db()->prepare(
  'SELECT c.id, c.user_id, c.body, c.created_at, u.username, u.nickname, u.avatar
   FROM comments c JOIN users u ON u.id = c.user_id
   WHERE c.log_id = ? ORDER BY c.id ASC'
);
$st->execute([$log_id]);

$comments = [];
foreach ($st as $r) {
  $cUserId = (int)$r['user_id'];
  $comments[] = [
    'id'        => (int)$r['id'],
    'userId'    => $cUserId,
    'username'  => $r['username'],
    'nickname'  => $r['nickname'],
    'avatarUrl' => avatar_url($r['avatar'] ?? null),
    'body'      => $r['body'],
    'createdAt' => $r['created_at'],
    // コメント本人 or 投稿の持ち主なら削除できる
    'canDelete' => ($viewerId > 0 && ($viewerId === $cUserId || $viewerId === $ownerId)),
  ];
}

json_out(['ok' => true, 'comments' => $comments]);
