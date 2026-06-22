<?php
// 現在のログイン状態とCSRFトークンを返す。
// 各ページ読み込み時に最初に呼び、ログイン中ならユーザー情報、未ログインならnull。
// 返ってきた csrf を、以降のPOST（登録/ログイン/記録など）のヘッダ X-CSRF-Token に付ける。
require __DIR__ . '/lib.php';
json_out([
  'ok'   => true,
  'user' => current_user(),   // 未ログインなら null
  'csrf' => csrf_token(),
]);
