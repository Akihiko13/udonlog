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

// 入力チェック
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('メールアドレスの形式が正しくありません');
if (mb_strlen($pass) < 8)   json_error('パスワードは8文字以上にしてください');
if (mb_strlen($pass) > 200) json_error('パスワードが長すぎます');

// ありがちで危険なパスワードはサーバー側でも拒否（フロントのチェックをすり抜けた場合の保険）
$COMMON_PASSWORDS = [
  'password','password1','password123','passw0rd','12345678','123456789','1234567890',
  'qwerty','qwertyui','qwerty123','11111111','00000000','88888888','12341234',
  'abcd1234','abc12345','1q2w3e4r','1qaz2wsx','asdfghjk','zxcvbnm','qazwsxedc',
  'iloveyou','letmein','letmein1','welcome','welcome1','admin123','administrator',
  'sunshine','princess','football','baseball','monkey12','dragon12','starwars',
  'trustno1','superman','batman12','master12','shadow12','google12','whatever',
  'udonudon','udon1234','sanuki','sanukiudon','udolog','udolog123','kagawa1234',
];
if (in_array(mb_strtolower($pass), $COMMON_PASSWORDS, true)) {
  json_error('このパスワードは推測されやすいため使えません。別のパスワードにしてください');
}
if ($nick === '')           json_error('ニックネームを入力してください');
if (mb_strlen($nick) > 50)  json_error('ニックネームは50文字以内にしてください');
if (mb_strlen($city) > 20)  json_error('市町名が長すぎます');

$pdo = db();

// メール重複チェック
$st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$st->execute([$email]);
if ($st->fetch()) json_error('このメールアドレスは既に登録されています', 409);

// 登録（password_hashでハッシュ化して保存）
$hash = password_hash($pass, PASSWORD_DEFAULT);
$pdo->beginTransaction();
try {
  $st = $pdo->prepare('INSERT INTO users (email, password_hash, nickname, city) VALUES (?,?,?,?)');
  $st->execute([$email, $hash, $nick, ($city !== '' ? $city : null)]);
  $uid = (int)$pdo->lastInsertId();

  // ログイン手段（パスワード）を記録 → 将来のGoogle/X連携と同じ仕組みで管理
  $st = $pdo->prepare('INSERT INTO auth_identities (user_id, provider, provider_uid) VALUES (?, "password", ?)');
  $st->execute([$uid, $email]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  json_error('登録に失敗しました。時間をおいて再度お試しください。', 500);
}

login_user($uid);
json_out([
  'ok'   => true,
  'user' => ['id' => $uid, 'email' => $email, 'nickname' => $nick, 'city' => ($city !== '' ? $city : null)],
  'csrf' => csrf_token(),
]);
