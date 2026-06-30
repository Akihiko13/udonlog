<?php
// 記録に写真を1枚アップロードする（ログイン必須・本人の記録のみ）。
// multipart/form-data でファイル項目 "photo"、log_id を受け取る。
// 安全のため画像を検証し、最大1200pxのJPEGに再エンコードして保存する。
require __DIR__ . '/lib.php';
require_post();
require_csrf();
$u = require_login();

if (!function_exists('imagecreatetruecolor')) {
  json_error('サーバーで画像処理が利用できません（GD未対応）', 500);
}

$logId = (int)($_POST['log_id'] ?? 0);
if ($logId <= 0) json_error('記録が指定されていません');

// その記録が本人のものか確認
$st = db()->prepare('SELECT id FROM logs WHERE id = ? AND user_id = ?');
$st->execute([$logId, $u['id']]);
if (!$st->fetch()) json_error('対象の記録が見つかりません', 404);

// アップロードの基本チェック
if (empty($_FILES['photo']) || !is_array($_FILES['photo'])) json_error('画像が送信されていません');
$f = $_FILES['photo'];
if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  json_error('アップロードに失敗しました（サイズが大きすぎる可能性があります）');
}
if (($f['size'] ?? 0) > 12 * 1024 * 1024) json_error('画像が大きすぎます（12MBまで）');
if (!is_uploaded_file($f['tmp_name'])) json_error('不正なアップロードです');

// 画像として正しいか検証し、種類を判定
$info = @getimagesize($f['tmp_name']);
if ($info === false) json_error('画像ファイルとして読み込めませんでした');
[$srcW, $srcH] = $info;
switch ($info[2]) {
  case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($f['tmp_name']); break;
  case IMAGETYPE_PNG:  $src = @imagecreatefrompng($f['tmp_name']);  break;
  case IMAGETYPE_GIF:  $src = @imagecreatefromgif($f['tmp_name']);  break;
  case IMAGETYPE_WEBP:
    $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false; break;
  default:
    json_error('対応していない画像形式です（JPEG / PNG / GIF / WEBP に対応）');
}
if (!$src) json_error('画像の読み込みに失敗しました');

// 長辺1200pxに収まるよう縮小（小さい画像はそのまま）
$max = 1200;
$scale = min(1, $max / max($srcW, $srcH));
$dstW = max(1, (int)round($srcW * $scale));
$dstH = max(1, (int)round($srcH * $scale));

$dst = imagecreatetruecolor($dstW, $dstH);
$white = imagecolorallocate($dst, 255, 255, 255);   // 透過は白背景でJPEG化
imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
imagedestroy($src);

// 保存先を用意（無ければ作成し、PHP実行を禁止）
$dir = __DIR__ . '/../uploads/records';
if (!is_dir($dir)) {
  if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
    imagedestroy($dst);
    json_error('保存先フォルダを作成できませんでした', 500);
  }
}
$ht = $dir . '/.htaccess';
if (!file_exists($ht)) {
  @file_put_contents($ht,
    "<FilesMatch \"\\.(php|phtml|php[0-9]|pl|py|cgi|asp|sh)$\">\n" .
    "  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n" .
    "  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n" .
    "</FilesMatch>\n"
  );
}

$path = $dir . '/' . $logId . '.jpg';
if (!imagejpeg($dst, $path, 82)) {
  imagedestroy($dst);
  json_error('画像の保存に失敗しました', 500);
}
imagedestroy($dst);

// 記録の写真枚数を1にしておく（互換のため）
$st = db()->prepare('UPDATE logs SET photo_count = 1 WHERE id = ? AND user_id = ?');
$st->execute([$logId, $u['id']]);

json_out(['ok' => true, 'photoUrl' => record_photo_url($logId)]);
