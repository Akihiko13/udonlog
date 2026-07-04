// ===========================================================================
// うどログ 共通サイトヘッダー（全ページ共通）
// ---------------------------------------------------------------------------
// 各ページの <head> 内で <script src="header.js"></script> を読み込むと、
// ページ最上部に共通ヘッダー（ロゴ＋ナビ）が表示されます。
// ※ <head> で読むことで、ヘッダー分の余白(padding-top)をCSSで最初から確保し、
//   本文がガクッと下がる（レイアウトシフト）のを防ぎます。
// ヘッダーの内容・デザインを変えたいときは、このファイル1つを編集すればOK。
// ===========================================================================
(function () {
  // 前回のログイン状態を覚えておくキー（ナビの初期表示に使う）
  var AUTH_CACHE = 'ulog_authed';

  // --- ヘッダー用スタイル（他ページのCSSと衝突しないよう ulog- 接頭辞で統一）---
  // body の padding-top をここで確保しておくことで、ヘッダー挿入時に本文が
  // ガクッと下がるのを防ぐ（JSでの後付けをやめCSSで最初から確保）。
  var css = `
    body { padding-top: 54px; }
    .ulog-header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 500;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      height: 54px;
      padding: 0 1.25rem;
      background: rgba(250,250,248,0.92);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-bottom: 0.5px solid #e5e5e0;
      font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
    }
    .ulog-logo {
      display: flex;
      align-items: center;
      gap: 7px;
      font-size: 16px;
      font-weight: 700;
      color: #1a1a1a;
      text-decoration: none;
      flex-shrink: 0;
    }
    .ulog-nav {
      display: flex;
      align-items: center;
      gap: 1.25rem;
      /* ページ独自の nav{} 指定（padding/背景/高さ等）が漏れてこないようリセット */
      margin: 0;
      padding: 0;
      height: auto;
      background: none;
      border: none;
      box-shadow: none;
      position: static;
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
    }
    .ulog-nav a {
      font-size: 13px;
      color: #555;
      text-decoration: none;
      white-space: nowrap;
    }
    .ulog-nav a:hover { color: #1a1a1a; }
    .ulog-cta {
      background: #8A5510;
      color: #fff !important;
      padding: 7px 16px;
      border-radius: 99px;
      font-weight: 600;
    }
    .ulog-cta:hover { background: #6F440C; }

    @media (max-width: 560px) {
      .ulog-nav { gap: 0.85rem; }
      .ulog-nav a:not(.ulog-cta) { font-size: 12px; }
      .ulog-cta { padding: 6px 13px; }
    }
    @media (max-width: 380px) {
      .ulog-nav a.ulog-hide-sm { display: none; }
    }

    /* --- 下部タブバー（モバイルのみ表示） --------------------------------- */
    .ulog-tabbar {
      position: fixed;
      /* ページ独自の nav{ position:sticky; top:54px } 等が漏れて上部に出ないよう
         top/margin/padding/border を明示的にリセットする（.ulog-nav と同じ方針）*/
      top: auto;
      left: 0;
      right: 0;
      bottom: 0;
      margin: 0;
      z-index: 500;
      display: none;                 /* 既定は非表示。モバイル幅でのみ表示 */
      justify-content: space-around;
      align-items: center;
      /* box-sizing はページ側の *{border-box} に依存せず自前で固定。
         高さはアイコン用の58px＋セーフエリア分を加算し、その分を padding-bottom で
         確保する。こうしないと（border-box時）セーフエリア分だけ中身が縮み、
         アイコンが上にはみ出る。 */
      box-sizing: border-box;
      height: calc(58px + env(safe-area-inset-bottom));
      padding: 0 0 env(safe-area-inset-bottom);   /* 下部はiPhoneのホームバー分 */
      background: rgba(250,250,248,0.95);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: none;
      border-top: 0.5px solid #e5e5e0;
      box-shadow: none;
      font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
    }
    .ulog-tab {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 3px;
      height: 100%;
      min-width: 0;
      color: #9a9a90;
      text-decoration: none;
      font-size: 10px;
      font-weight: 600;
    }
    .ulog-tab i { font-size: 23px; line-height: 1; }
    .ulog-tab span { line-height: 1; }
    .ulog-tab.active { color: #8A5510; }
    .ulog-tab:active { opacity: 0.6; }

    /* 記録用の浮動ボタン（FAB）。タブバーの上・右下に浮かせる（X本家と同じ発想）。
       表示可否は renderTabbar が .ulog-fab-on の付け外しで制御する。*/
    .ulog-fab {
      display: none;                 /* 既定は非表示 */
      position: fixed;
      right: 16px;
      bottom: calc(58px + env(safe-area-inset-bottom) + 16px);  /* タブバーの少し上 */
      z-index: 501;                  /* タブバー(500)より前 */
      align-items: center;
      justify-content: center;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: #BA7517;
      color: #fff !important;
      text-decoration: none;
      box-shadow: 0 4px 14px rgba(186,117,23,0.45);
    }
    .ulog-fab i { font-size: 28px; line-height: 1; color: #fff; }
    .ulog-fab:active { background: #8A5510; }

    @media (max-width: 640px) {
      .ulog-tabbar { display: flex; }
      /* タブバーの高さぶん本文下部に余白を確保（バーを出すページだけ） */
      body.ulog-has-tabbar { padding-bottom: calc(62px + env(safe-area-inset-bottom)); }
      /* モバイルでは上部ヘッダーのナビを隠して「うどログ」ロゴだけ残す。
         通常ページはナビを下部タブバーへ集約。認証ページ(タブバー無し)も
         ログイン等に集中できるようナビは出さない（ロゴからホームへは戻れる）。*/
      .ulog-nav { display: none; }
      /* 記録FABはモバイルかつ表示ONのときだけ出す */
      .ulog-fab.ulog-fab-on { display: flex; }
    }
  `;

  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  // --- ヘッダー本体を body 先頭に挿入。<head> から読まれて body が未構築の
  //     場合は DOMContentLoaded を待ってから挿入する ---
  function insertHeader() {
    var header = document.createElement('header');
    header.className = 'ulog-header';
    header.innerHTML =
      '<a href="/" class="ulog-logo">うどログ</a>' +
      '<nav class="ulog-nav" id="ulog-nav"></nav>';
    document.body.insertBefore(header, document.body.firstChild);

    // 下部タブバー（モバイル用）を body 末尾に挿入。認証系ページでは出さない。
    insertTabbar();

    // 前回のログイン状態をキャッシュから復元して即描画（チラつき防止）。
    // その後 API で実際の状態を確認し、変わっていれば差し替える。
    var cached = null;
    try { cached = localStorage.getItem(AUTH_CACHE); } catch (e) {}
    var cachedUser = cached === '1' ? {} : null;   // {} は「ログイン中レイアウト」用のダミー
    renderNav(cachedUser);
    renderTabbar(cachedUser);

    if (typeof API !== 'undefined') {
      API.refresh().then(function (d) {
        renderNav(d.user);
        renderTabbar(d.user);
        try { localStorage.setItem(AUTH_CACHE, d.user ? '1' : '0'); } catch (e) {}
      }).catch(function () {});
    }
  }

  // --- 下部タブバー ---------------------------------------------------------
  // ログイン/登録などの集中フローでは邪魔になるので出さない
  function isAuthPage() {
    return /(^|\/)(login|register|forgot-password|reset-password)\.html$/.test(location.pathname);
  }

  // 現在ページがどのタブに当たるか（アクティブ表示用）
  function currentTab() {
    var p = location.pathname;
    if (/(^|\/)feed\.html$/.test(p)) return 'feed';
    if (/(^|\/)(shops|shop-detail)\.html$/.test(p) || /(^|\/)shops\//.test(p)) return 'shops';
    if (/(^|\/)record\.html$/.test(p)) return 'record';
    if (/(^|\/)(mypage|profile-edit|account)\.html$/.test(p)) return 'mypage';
    return '';
  }

  function insertTabbar() {
    if (isAuthPage()) return;
    if (document.getElementById('ulog-tabbar')) return;
    var bar = document.createElement('nav');
    bar.className = 'ulog-tabbar';
    bar.id = 'ulog-tabbar';
    bar.setAttribute('aria-label', 'メインメニュー');
    document.body.appendChild(bar);
    document.body.classList.add('ulog-has-tabbar');   // 本文下部の余白を有効化

    // 記録用の浮動ボタン（FAB）。表示可否は renderTabbar で切り替える。
    var fab = document.createElement('a');
    fab.className = 'ulog-fab';
    fab.id = 'ulog-fab';
    fab.href = 'shops.html';
    fab.setAttribute('aria-label', 'うどんを記録する');
    fab.innerHTML = '<i class="ti ti-plus"></i>';
    document.body.appendChild(fab);
  }

  function renderTabbar(user) {
    var bar = document.getElementById('ulog-tabbar');
    if (!bar) return;
    var cur = currentTab();
    function tab(href, key, icon, label) {
      var active = key === cur ? ' active' : '';
      return '<a class="ulog-tab' + active + '" href="' + href + '">' +
             '<i class="ti ti-' + icon + '"></i><span>' + label + '</span></a>';
    }
    if (user) {
      // ログイン中は移動用の3タブ。記録は右下のFAB（下記）に分離。
      bar.innerHTML =
        tab('feed.html', 'feed', 'home', '新着') +
        tab('shops.html', 'shops', 'search', '探す') +
        tab('mypage.html', 'mypage', 'user', 'マイページ');
    } else {
      bar.innerHTML =
        tab('feed.html', 'feed', 'home', '新着') +
        tab('shops.html', 'shops', 'search', '探す') +
        tab('login.html', 'login', 'login', 'ログイン') +
        tab('register.html', 'register', 'user-plus', '登録');
    }

    // 記録FAB：ログイン中かつ「探す/記録」ページ以外で表示（重複回避）。
    var fab = document.getElementById('ulog-fab');
    if (fab) {
      var showFab = !!user && cur !== 'shops' && cur !== 'record';
      fab.classList.toggle('ulog-fab-on', showFab);
    }
  }

  // --- ナビの描画（ログイン状態で出し分け）---
  function renderNav(user) {
    var nav = document.getElementById('ulog-nav');
    if (!nav) return;
    if (user) {
      nav.innerHTML =
        '<a href="feed.html">新着</a>' +
        '<a href="shops.html" class="ulog-hide-sm">お店を探す</a>' +
        '<a href="mypage.html">マイページ</a>' +
        '<a href="#" id="ulog-logout">ログアウト</a>';
      var lo = document.getElementById('ulog-logout');
      lo.addEventListener('click', function (e) {
        e.preventDefault();
        (async function () {
          try { await API.refresh(); await API.post('logout.php', {}); } catch (_) {}
          try { localStorage.setItem(AUTH_CACHE, '0'); } catch (_) {}
          location.href = '/';
        })();
      });
    } else {
      nav.innerHTML =
        '<a href="feed.html">新着</a>' +
        '<a href="shops.html" class="ulog-hide-sm">お店を探す</a>' +
        '<a href="login.html">ログイン</a>' +
        '<a href="register.html" class="ulog-cta">無料登録</a>';
    }
  }

  if (document.body) {
    insertHeader();
  } else {
    document.addEventListener('DOMContentLoaded', insertHeader);
  }
})();
