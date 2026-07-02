<?php
// プロフィール（ニックネーム・活動エリア・Xハンドル・自己紹介）を更新（ログイン必須）
// ※メールアドレス・パスワードはここでは変更しない（別フロー）。
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

$in   = body();
$nick = trim((string)($in['nickname'] ?? ''));
$username = normalize_username((string)($in['username'] ?? ''));
$city = trim((string)($in['city'] ?? ''));
$xh   = ltrim(trim((string)($in['x_handle'] ?? '')), '@');   // 先頭の@は除去
$bio  = trim((string)($in['bio'] ?? ''));

// 入力チェック（register.php と同じ基準）
if ($nick === '')           json_error('ニックネームを入力してください');
if (mb_strlen($nick) > 50)  json_error('ニックネームは50文字以内にしてください');
if ($uerr = username_error($username)) json_error($uerr);
// ユーザー名の重複チェック（自分以外）
$chk = db()->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
$chk->execute([$username, $u['id']]);
if ($chk->fetch()) json_error('このユーザー名は既に使われています', 409);
if (mb_strlen($city) > 20)  json_error('活動エリア名が長すぎます');
if ($xh !== '' && !preg_match('/^[A-Za-z0-9_]{1,15}$/', $xh)) {
  json_error('Xのユーザー名は半角英数字とアンダースコア（_）のみ・15文字以内で入力してください');
}
if (mb_strlen($bio) > 500)  json_error('自己紹介は500文字以内にしてください');

$st = db()->prepare('UPDATE users SET username = ?, nickname = ?, city = ?, x_handle = ?, bio = ? WHERE id = ?');
$st->execute([
  $username,
  $nick,
  ($city !== '' ? $city : null),
  ($xh !== '' ? $xh : null),
  ($bio !== '' ? $bio : null),
  $u['id'],
]);

json_out([
  'ok'   => true,
  'user' => current_user(),   // 更新後の最新情報を返す
]);
