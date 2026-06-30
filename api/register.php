<?php
// 会員登録：メール＋パスワード＋ニックネーム（＋市町）
require __DIR__ . '/lib.php';
require_post();
require_csrf();

$in    = body();
$email = trim((string)($in['email'] ?? ''));
$pass  = (string)($in['password'] ?? '');
$nick  = trim((string)($in['nickname'] ?? ''));
$city  = trim((string)($in['city'] ?? ''));
$xh    = ltrim(trim((string)($in['x_handle'] ?? '')), '@');   // 先頭の@は除去
$bio   = trim((string)($in['bio'] ?? ''));

// 入力チェック
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('メールアドレスの形式が正しくありません');

// メール確認の完了をサーバー側で強制（コード認証をスキップした直接登録を防ぐ）
if (empty($_SESSION['verified_email']) ||
    mb_strtolower($_SESSION['verified_email']) !== mb_strtolower($email)) {
  json_error('メールアドレスの確認が完了していません。確認コードの入力からやり直してください。', 403);
}
if (mb_strlen($pass) < 8)   json_error('パスワードは8文字以上にしてください');
if (mb_strlen($pass) > 200) json_error('パスワードが長すぎます');

// ありがちで危険なパスワードはサーバー側でも拒否（フロントのチェックをすり抜けた場合の保険）
if (is_weak_password($pass)) {
  json_error('このパスワードは推測されやすいため使えません。別のパスワードにしてください');
}
if ($nick === '')           json_error('ニックネームを入力してください');
if (mb_strlen($nick) > 50)  json_error('ニックネームは50文字以内にしてください');
if (mb_strlen($city) > 20)  json_error('活動エリア名が長すぎます');
if (mb_strlen($bio) > 500)  json_error('自己紹介は500文字以内にしてください');
// Xユーザー名（任意）：半角英数字とアンダースコアのみ・15文字以内
if ($xh !== '' && !preg_match('/^[A-Za-z0-9_]{1,15}$/', $xh)) {
  json_error('Xのユーザー名は半角英数字とアンダースコア（_）のみ・15文字以内で入力してください');
}

$pdo = db();

// メール重複チェック
$st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$st->execute([$email]);
if ($st->fetch()) json_error('このメールアドレスは既に登録されています', 409);

// 登録（password_hashでハッシュ化して保存）
$hash = password_hash($pass, PASSWORD_DEFAULT);
$pdo->beginTransaction();
try {
  $st = $pdo->prepare('INSERT INTO users (email, password_hash, nickname, city, x_handle, bio) VALUES (?,?,?,?,?,?)');
  $st->execute([$email, $hash, $nick, ($city !== '' ? $city : null), ($xh !== '' ? $xh : null), ($bio !== '' ? $bio : null)]);
  $uid = (int)$pdo->lastInsertId();

  // ログイン手段（パスワード）を記録 → 将来のGoogle/X連携と同じ仕組みで管理
  $st = $pdo->prepare('INSERT INTO auth_identities (user_id, provider, provider_uid) VALUES (?, "password", ?)');
  $st->execute([$uid, $email]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  json_error('登録に失敗しました。時間をおいて再度お試しください。', 500);
}

// 使い終わった確認コードとセッションの確認済みフラグを片付ける
$st = $pdo->prepare('DELETE FROM email_verifications WHERE email = ?');
$st->execute([$email]);
unset($_SESSION['verified_email']);

login_user($uid);
json_out([
  'ok'   => true,
  'user' => ['id' => $uid, 'email' => $email, 'nickname' => $nick, 'city' => ($city !== '' ? $city : null), 'x_handle' => ($xh !== '' ? $xh : null)],
  'csrf' => csrf_token(),
]);
