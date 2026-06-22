# うどログ 会員機能API（PHP + MySQL / Xサーバー）

静的サイト（HTML/JS）から `fetch` で呼ぶJSON APIです。GitHub Pagesでは動きません（PHPが必要）。Xサーバーに置いて動かします。

## セットアップ手順（Xサーバー）

1. **データベース作成**：サーバーパネル →「MySQL設定」でDBとDBユーザーを作成し、ユーザーをDBに追加
2. **テーブル作成**：phpMyAdmin で `schema.sql` を実行
3. **接続設定**：`config.sample.php` を `config.php` にコピーし、DB名・ユーザー・パスワードを記入
   → `config.php` は **SFTPでサーバーに直接アップロード**（Gitには上げない）
4. このフォルダ一式を公開ディレクトリ配下（例：`/udonlog/api/`）に配置
5. ブラウザで `https://（ドメイン）/api/me.php` を開き `{"ok":true,"user":null,...}` が出れば成功

## エンドポイント（現状）

| ファイル | メソッド | 役割 |
|---|---|---|
| `me.php` | GET | ログイン状態とCSRFトークンを返す（最初に呼ぶ） |
| `register.php` | POST | 会員登録 |
| `login.php` | POST | ログイン |
| `logout.php` | POST | ログアウト |

POST時は、`me.php` で得た `csrf` を HTTPヘッダ `X-CSRF-Token` に付け、ボディはJSONで送ります。

## 予定（次に追加）

- `logs_list.php` / `log_add.php`（記録の取得・追加）
- `rating_set.php`（おすすめ度）
- フロント側（record/mypage/shops/shop-detail/login/register）のAPI連携
- 後日：Google / X ログイン（`auth_identities` で対応済みの設計）

## セキュリティ

- パスワードは `password_hash()`、SQLはプリペアドステートメント
- CSRFトークン、セッションCookieは HttpOnly + Secure + SameSite=Lax
- 公開は必ず HTTPS
