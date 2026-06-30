<?php
// Googleログイン：フロントから受け取ったIDトークンを検証し、ログイン／新規作成する。
require __DIR__ . '/lib.php';
require_post();
require_csrf();

$clientId = $CONFIG['google_client_id'] ?? '';
if ($clientId === '') json_error('Googleログインは現在利用できません', 500);

$in   = body();
$cred = trim((string)($in['credential'] ?? ''));
if ($cred === '') json_error('認証情報が送信されていません');

// --- IDトークンを Google の tokeninfo で検証して情報を取り出す ---
function http_get_json(string $url): ?array {
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 10,
      CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code !== 200) return null;
    $d = json_decode($res, true);
    return is_array($d) ? $d : null;
  }
  $res = @file_get_contents($url);
  if ($res === false) return null;
  $d = json_decode($res, true);
  return is_array($d) ? $d : null;
}

$info = http_get_json('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($cred));
if (!$info || empty($info['sub'])) {
  json_error('Google認証の検証に失敗しました。もう一度お試しください。', 401);
}
// 発行先（このアプリ向けか）と発行者を確認
if (($info['aud'] ?? '') !== $clientId) json_error('Google認証が無効です。', 401);
$iss = $info['iss'] ?? '';
if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com') {
  json_error('Google認証が無効です。', 401);
}
if (isset($info['exp']) && (int)$info['exp'] < time()) {
  json_error('Google認証の有効期限が切れています。もう一度お試しください。', 401);
}

$sub           = (string)$info['sub'];                       // GoogleアカウントのユニークID
$email         = strtolower(trim((string)($info['email'] ?? '')));
$ev            = $info['email_verified'] ?? 'false';
$emailVerified = ($ev === true || $ev === 'true');
$name          = trim((string)($info['name'] ?? ''));

$pdo = db();

// 1) 既にGoogle連携済みならそのままログイン
$st = $pdo->prepare('SELECT user_id FROM auth_identities WHERE provider = "google" AND provider_uid = ?');
$st->execute([$sub]);
$row = $st->fetch();
if ($row) {
  login_user((int)$row['user_id']);
  json_out(['ok' => true, 'user' => current_user(), 'csrf' => csrf_token()]);
}

// 2) 同じメールの既存会員があれば連携、なければ新規作成
$isNew = false;
$pdo->beginTransaction();
try {
  $uid = null;

  if ($email !== '' && $emailVerified) {
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u) $uid = (int)$u['id'];
  }

  if (!$uid) {
    $isNew = true;
    // 新規会員（パスワードなし＝OAuth専用）。ニックネームはGoogleの名前から。
    $nick = $name !== '' ? mb_substr($name, 0, 50)
          : ($email !== '' ? explode('@', $email)[0] : 'うどん好き');
    $st = $pdo->prepare('INSERT INTO users (email, password_hash, nickname) VALUES (?, NULL, ?)');
    $st->execute([($email !== '' ? $email : null), $nick]);
    $uid = (int)$pdo->lastInsertId();
  }

  // Google連携を追加
  $st = $pdo->prepare('INSERT INTO auth_identities (user_id, provider, provider_uid) VALUES (?, "google", ?)');
  $st->execute([$uid, $sub]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  json_error('アカウントの作成に失敗しました。時間をおいて再度お試しください。', 500);
}

login_user($uid);
json_out(['ok' => true, 'user' => current_user(), 'csrf' => csrf_token(), 'isNew' => $isNew]);
