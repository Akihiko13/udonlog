# うどログ 開発ガイド（引き継ぎ用）

香川のさぬきうどんを記録・共有する会員制サービス。本番: **https://udolog.com**（エックスサーバー稼働・公開済み）。
このファイルは開発を引き継ぐための要点集。詳しい変更履歴は `DEVLOG.md`、ユーザー向け告知は `news.js`。

## 製品の方向性
**「X ＋ 食べログ の香川うどん屋版」**。記録（スタンプ・制覇率・達成バッジ）という独自のゲーム性を核に、
X的な交流（新着フィード・フォロー・コメント・いいね・通知）と、食べログ的な店探しを足していく。
スタンプ/制覇率/バッジは最大の差別化なので捨てない。

## 技術構成
- **素の HTML + PHP + MySQL**（フレームワーク無し）。SPAではなく**ページ単位**の構成。
- フロントは各HTMLに `<style>`/`<script>` を直書き。**共有部品はJSでDOM注入**（下記）。
- 本番のみ PHP/MySQL が動く（**ローカルにPHP/MySQLは無い**）。

### 共有JS（全ページ共通・DOM注入型。これらを直せば全ページに反映）
- `api.js` … フロントのAPIラッパー。`await API.refresh()`（me.php でログイン状態＋CSRF取得）→ `API.get/post/upload`、`API.user`、`API.isLoggedIn()`。POSTは自動でCSRF付与。
- `header.js` … **共通ヘッダー＋下部タブバー（モバイル）＋記録FAB＋お店選びシート＋通知ベル＋共通フッター**を全部注入。ヘッダー/タブ/フッターを変えたい時はここ1ファイル。
- `toast.js` … `showToast(msg, {error:true})`。alert の代わり。
- `time.js` … 相対時間ヘルパー。`ulogTimeAgo(d)`（たった今/N分前/N時間前/昨日/N日前・1週間以上前は日付）/ `ulogDateFull(d)`（ツールチップ用）/ `ulogDate(d)`。MySQLのdatetimeを iOS Safari でもパースできるよう空白→T置換を内包。**comments.js より前に読み込むこと**。新着フィード・店詳細の投稿・コメント・通知で使用。
- `comments.js` … 投稿コメントのインライン展開（`ulogCmtToggle(logId)` 等）。投稿カードに「💬ボタン＋`ulog-cmt-box-{logId}`」を置けば動く。
- `shops-data.js` … 店舗マスタ（`const SHOPS`）。**自動生成物**（後述）。
- `landmarks.js` … 観光地・駅マスタ（`const LANDMARKS`）。「スポットから近い順」検索用。`landmarks.csv`＋`build-landmarks.py`で生成（**自動生成物**・shops.htmlのみ使用）。
- `news.js` … お知らせデータ（`const NEWS`、新しい順に先頭へ追加）。
- `google-auth.js` / `analytics.js` … Googleログイン / GA4。
- `map.js` … 地図共通部品（`UdonMap`）。**地図プロバイダに依存しない自前API**（`ensureLoaded/create/setMarkers/fitBounds/panTo/setUserMarker`）で包む層。いまは Google Maps JS API 実装。**将来Leaflet+OSM等に載せ替える時はこのファイルの中身だけ差し替え**れば呼び出し側(shops/shop-detail)は無改修。キーは `config.php`(`maps_api_key`)→`me.php`→`API.mapsApiKey` 経由。**キー未設定なら地図UIは自動で隠れる**（縮退）。使うページで `shops-data.js` の後に読み込む。

### ページ（*.html）
index(LP) / feed(新着) / shops(お店を探す=記録の入口) / ranking(香川うどんランキング) / shop-detail(店詳細) / record(記録投稿) /
mypage / user(公開プロフィール `/＜username＞`) / profile-edit / account / notifications(通知) / follows(フォロワー/フォロー中一覧) /
login / register / forgot-password / reset-password / news / privacy / terms

### API（api/*.php）— 規約は `lib.php` に集約
- ヘルパー: `require_login()` / `require_post()` / `require_csrf()` / `body()` / `json_out()` / `json_error(msg,status)` / `db()`(PDO) / `current_user()` / `avatar_url()` / `record_photo_url()` / `notify()` / `unread_notif_count()`。
- 新エンドポイントは `like_toggle.php` を雛形にすると早い（ログイン必須・CSRF・JSON）。
- 主要: me / login / logout / register / recent_logs(新着・`?following=1`でフォロー中) / shop_logs / my_logs / user_logs / ranking(`?type=rating|records`・ランキング集計) / log_add/update/delete/get / log_photo(_delete) / like_toggle / comment_list/add/delete / follow_toggle(user_id か username) / follows_list / notifications(_read) / rating_set / favorites_get/set / profile_update / avatar_set/delete / password_* / email_verify_* / google_auth。
- 設定は `config.php`（DB接続・Google Client ID・**Maps APIキー(`maps_api_key`)** 等、**gitignore・本番のみ**。雛形 `config.sample.php`）。地図キーはGoogleログインとは別キー推奨・**Cloud Console側でHTTPリファラー制限(udolog.com)＋Maps JavaScript APIのみに制限**して発行する（未設定でも `?? ''` で壊れない＝地図が出ないだけ）。

### DB（api/schema.sql・InnoDB/utf8mb4）
テーブル: `users, auth_identities, logs, ratings, password_resets, favorite_shops, likes, comments, follows, notifications, email_verifications, auth_tokens`。
- 投稿=`logs`（1杯1件）。`logs.comment` は投稿者自身のひとこと感想（≠返信）。写真は別ファイル保存（`record_photo_url`）。
- 交流: `likes` / `comments`(本文140字) / `follows` / `notifications`(type=comment|follow|like)。
- ログイン保持: `auth_tokens`（remember me・selector/validator方式・validatorはSHA-256保存・60日）。`lib.php` が冒頭 `try_remember_login()` でセッション無時に自動復元。発行=login_user共通、失効=logout/退会/パスワード変更・再設定。**本番は先に `api/migrate_auth_tokens.sql` を実行**。
- **スキーマ変更は phpMyAdmin で手動適用**。追加分は `api/migrate_*.sql`（comments/follows/notifications）を用意。新テーブルを足したら migrate_*.sql も作り、引き継ぎ相手に「先にDB実行」を明示すること。

## 店舗データの更新フロー
1. `shops.csv` を編集（列: id,name,kana,city,type,dish,hours,closed,status,blog,parking,slug,lat,lng。type=セルフ/一般/製麺所、status空=営業中/「閉店」）。
2. （座標）新店を足したら `python3 geocode-shops.py` で `lat`/`lng` を付与（「現在地から近い順」用）。Google Geocoding APIを使うので `export GOOGLE_GEOCODING_API_KEY="キー"` が必要（**キーは環境変数のみ・gitに含めない**）。空欄の店だけ処理する再実行前提。精度が粗い店・失敗店はログに出るので手直しする。
3. `python3 build-shops-data.py` を実行 → `shops-data.js` を再生成（現在 **245店**）。lat/lng があれば数値として出力される。
   - 観光地（「スポットから近い順」用）を足す時は `landmarks.csv` を編集 → `python3 build-landmarks.py`（座標未取得ぶんがあれば同じくキーが必要・引きにくい施設は script内 `QUERY_OVERRIDE`）→ `landmarks.js` 再生成。座標付きの観光地だけ検索候補に出る。
4. `sitemap.xml` に新店URL `https://udolog.com/shops/{slug}` を追記（自動生成スクリプトは無い＝手動）。

## アイコン（Tabler サブセット・自前ホスト）
- アイコンはCDNではなく**サブセットフォント自前ホスト**（`tabler-icons.css` + `fonts/*.woff2`、使用56種のみ・約10KB）。
- **新しい `ti-*` アイコンをページに追加したら `python3 build-tabler-subset.py` を再実行**して両生成物を再アップ（`pip3 install --user fonttools brotli` が必要）。
- JSで名前を変数から組み立てていて「ti-」の文字が現れない場合（header.jsのタブ等）は、スクリプト内の `EXTRA` に手で追加。
- `ti-*-filled` はTabler 3.xで別フォント（filled）に分離済み。スクリプトが自動で filled 側から取り込む。

## デプロイ（FileZilla → エックスサーバー）
ローカル→本番の対応:
- `udonlog/` 直下（*.html, *.js, 画像, favicon類, sitemap.xml 等）→ **`public_html/` 直下**
- `udonlog/api/*.php` → **`public_html/api/`**
- `udonlog/uploads/...` → **`public_html/uploads/...`**
- `DEVLOG.md` / `*.sql` / `build-shops-data.py` / `geocode-shops.py` / `build-landmarks.py` / `shops.csv` / `landmarks.csv` はWebに不要（DB用・開発用）。※ `landmarks.js` は shops.html が読むのでアップ必要。

## ローカルでの動作確認（PHPが無い前提）
- 静的サーバー: `python3 -m http.server <port>`（udonlogディレクトリで）。Claude の Preview で開いて確認。
- API/DBは動かないので、**フロントはブラウザで API をモックして検証**（例: `API.get=()=>Promise.resolve({...})`）。バックエンドPHPは `lib.php` の規約に厳密に合わせて担保。
- ※Previewタブがseedサーバーに戻ることがある。都度 `location.href='http://localhost:<port>/xxx.html'` で開き直す。localStorage `ulog_authed=1` を**対象オリジンで**セット→reload するとログイン中レイアウトを再現できる。

## 作業ルール（重要・ユーザー指示）
- **変更のたびに git commit**（指示不要）。ブランチはmainのまま可、pushは継続。コミット文は日本語＋末尾に `Co-Authored-By: Claude <...>`。
- **変更後は毎回、FileZillaアップロード一覧を提示**。並び順は **①アップ先フォルダごと（public_html直下→api→uploads）→ ②同フォルダ内は拡張子ごと（html→js→php等）→ ③A→Z**。各行にアップ先を明記。新規/上書きも明記。DB変更があれば「先にSQL実行」を明示。
- **DEVLOGの日付は git履歴/mtimeで実証してから**書く（推測しない）。
- **お知らせ(news.js)** はユーザーに嬉しい変化だけを平易な言葉で。内部修正はDEVLOGのみ。
- 図表は**頼まれた時だけ**作る（トークン節約）・文字は黒(#222)・中間ファイルを残さない。

## デザイン規範
- カラー: アンバー系（`--amber:#BA7517` / `--amber-dark:#8A5510` / hover `#6F440C`）、背景 `#FAFAF8`、本文 `#1a1a1a`。リンク/CTAは `#8A5510`（WCAG AA）。
- アイコンは Tabler（`ti ti-*`）。マスコット「うどまる」SVGを空状態に。
- モバイルは下部タブバー（新着/探す/通知/マイページ）＋記録FAB。上部ナビはモバイルで隠しロゴのみ。

## 実装済みの主な機能（2026-07時点）
記録・スタンプ帳・達成バッジ(4軸25種)・エリア制覇率 / 会員登録(メール認証・Googleログイン)・公開プロフィール(username URL) /
新着フィード(無限スクロール)・お店の詳細(みんなの投稿・人気メニュー・おすすめ度) / いいね・コメント(140字)・フォロー(新着に「フォロー中」タブ)・フォロワー/フォロー中一覧・通知(ベル＋未読バッジ)・相対時間表示(フィード/投稿/コメント/通知)。

### 地図・フィード・その他（2026-07-13〜15 追加）
- **地図表示（Google Maps）**：`map.js`(UdonMap・プロバイダ非依存の抽象化層)。お店を探す(shops.html)に一覧/地図トグル＝絞り込み結果をピンに反映・**記録済み=緑/未記録=アンバーで色分け＋凡例**・**地図右下に現在地ボタン常設**。店舗詳細に単店地図＋「ルートを見る」＋Googleマップ店ページ導線。キーは config.php(`maps_api_key`)→me.php→API.mapsApiKey。※**クラスタリングは見送り**（店400軒〜or不便の声で着手）。
- **ログイン保持（remember me）**：上記DB参照。
- **新着フィード**：先頭に「人気店ランキングTOP3」カード(記録数・閉店除外・3件以上で表示)。**PC(≥900px)のみ右サイドバー**(ランキング＋記録CTA)で500px列の左右余白を活用（`.container`は`flex:0 0 500px`固定、モバイルは従来の単カラム）。
- **お店詳細「みんなの投稿」**：写真グリッド(3枚以上で3列サムネ・タップで拡大・9枚超は+N)＋従来の投稿リストの二段構え。
- **404ページ**：`404.html`(うどまる＋導線・共通ヘッダー/フッター)。`.htaccess`に`ErrorDocument 404 /404.html`。

### お店を探す(shops.html)の検索・絞り込み（2026-07-11 追加）
- **絞り込み**：エリア・種類（既存）＋「こだわり」(今営業中／駐車場あり)＋「記録」(まだ行ってない／記録済み・ログイン中のみ)。
- **営業中/時間外/定休日バッジ**：hours/closed から現在の営業状態を推定して一覧カード・店舗詳細に表示（判定関数 `openState` 等を両ファイルに配置。目安である旨の注記あり。営業中の緑ドットは脈動アニメ）。
- **並び替え**：五十音順（既定）／「現在地から近い順に並べる」ボタン（Haversine・位置情報は許可制／保存なし）。
- **スポット（観光地・駅）から近い順**：`landmarks.js`(43件)の座標を基準に近い順。基準点は現在地/スポットを `nearRef` で切替、状態はバナー＋「現在地から近い順番に並べ替える」/「解除」で操作。座標付きスポットのみ候補表示。
- **記録済み/まだ行ってない**：ログイン中は記録済み店に緑「✓記録済み」バッジ＋色分け、絞り込みも可。

## 未実装の候補（優先度目安）
- 行きたいリスト（保存）
- ローディングのうどまる化（現在は ti-loader-2。後回し中）
- マーカークラスタリング（見送り中・店400軒〜or不便の声で着手）
- Google連携のみユーザーの「パスワード新規設定」（forgot フローの拡張が最小コスト）
- 観光地「スポットから近い順」の任意地点対応（現状は事前登録リストのみ。任意住所はGoogle都度課金＋キャッシュが要る）

## 引き継ぎ時の注意（本番未反映の状態・2026-07-15時点）
このセッションのコードは全て commit＋GitHub push 済みだが、**本番（エックスサーバー）へのアップロードは未実施**。次の対応が保留：
1. **remember me**：**先に `api/migrate_auth_tokens.sql` を phpMyAdmin で実行** → その後 `api/` の8ファイル(lib/login/logout/register/google_auth/password_change/password_reset/account_delete)をアップ。
2. 地図・フィード・404等のフロント変更ファイル群（*.html, map.js, shops-data.js, sitemap.xml, news.js, tabler-icons.css, fonts/, .htaccess, 404.html）を public_html にアップ。
3. Maps APIキーの1日クォータ上限は有料アカウント移行時（トライアル終了・2026-10-11頃）に設定（予算アラート¥1,000は設定済み）。

## ハマりどころメモ
- ページ独自の `nav{}`/`footer{}` が共有部品(`<nav class=ulog-*>`/`<footer class=ulog-footer>`)に漏れる → 共有側で主要プロパティを明示リセット済み。
- `hidden`属性は `.class{display:flex}` に負ける → バッジ類は `.xxx[hidden]{display:none}` を併記。
- iPhoneのセーフエリア: 固定バーは `height: calc(58px + env(safe-area-inset-bottom))` ＋ `padding-bottom: env(...)`（box-sizing:border-box）。
