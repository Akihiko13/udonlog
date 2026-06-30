<?php
// パスワード再設定の要求：メールアドレスを受け取り、再設定リンクをメール送信する。
// セキュリティ上、メールの存在有無に関わらず常に成功レスポンスを返す（会員の存在を漏らさない）。
require __DIR__ . '/lib.php';
require_post();
require_csrf();

// === 設定（必要に応じてここを変更）=========================================
const RESET_SITE_URL       = 'https://udolog.com';     // サイトのURL（末尾スラッシュなし）
const RESET_MAIL_FROM      = 'info@udolog.com';        // 差出人（実在するアドレスにする＝到達率のため）
const RESET_MAIL_FROM_NAME = 'udolog';                 // 差出人の表示名（文字化け回避のため半角）
const RESET_EXPIRE_MIN     = 60;                        // リンクの有効時間（分）
// ===========================================================================

$in    = body();
$email = trim((string)($in['email'] ?? ''));

// 形式が正しいメールのときだけ処理（不正でも成功レスポンスは返す）
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $st = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
  $st->execute([$email]);
  $u = $st->fetch();

  // 会員が存在し、パスワードログインを使っている場合のみ送信
  if ($u && !empty($u['password_hash'])) {
    $uid = (int)$u['id'];

    // 既存の未使用トークンは無効化（1通だけ有効にする）
    $st = db()->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL');
    $st->execute([$uid]);

    // 生トークンを発行し、ハッシュをDBに保存（生はメールにのみ載せる）
    $raw  = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $st = db()->prepare(
      'INSERT INTO password_resets (user_id, token_hash, expires_at)
       VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    );
    $st->execute([$uid, $hash, RESET_EXPIRE_MIN]);

    // メール送信
    $link = RESET_SITE_URL . '/reset-password.html?token=' . $raw;
    $subject = '【うどログ】パスワード再設定のご案内';
    $body =
      "うどログのパスワード再設定のご依頼を受け付けました。\n\n" .
      "下記のリンクを開いて、新しいパスワードを設定してください。\n" .
      "（このリンクの有効期限は " . RESET_EXPIRE_MIN . "分です）\n\n" .
      $link . "\n\n" .
      "※このメールに心当たりがない場合は、何もせずに破棄してください。\n" .
      "　パスワードは変更されません。\n\n" .
      "──────────────\n" .
      "うどログ\n" .
      RESET_SITE_URL . "\n";

    $headers =
      'From: ' . RESET_MAIL_FROM_NAME . ' <' . RESET_MAIL_FROM . ">\r\n" .
      'Reply-To: ' . RESET_MAIL_FROM;

    // 日本語メールを正しくエンコードして送信（件名・本文）
    mb_language('Japanese');
    mb_internal_encoding('UTF-8');
    @mb_send_mail($email, $subject, $body, $headers, '-f ' . RESET_MAIL_FROM);
  }
}

// 常に同じ成功レスポンス（存在するメールかどうかを隠す）
json_out(['ok' => true]);
