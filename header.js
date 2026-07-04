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
      height: 58px;
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

    /* 中央の「＋（記録）」ボタン */
    .ulog-tab-add {
      flex: 0 0 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 46px;
      height: 46px;
      margin: 0 4px;
      background: #BA7517;
      color: #fff !important;
      border-radius: 50%;
      text-decoration: none;
      box-shadow: 0 3px 10px rgba(186,117,23,0.35);
    }
    .ulog-tab-add i { font-size: 26px; line-height: 1; color: #fff; }
    .ulog-tab-add:active { background: #8A5510; }

    @media (max-width: 640px) {
      .ulog-tabbar { display: flex; }
      /* タブバーの高さぶん本文下部に余白を確保（バーを出すページだけ） */
      body.ulog-has-tabbar { padding-bottom: calc(62px + env(safe-area-inset-bottom)); }
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
      bar.innerHTML =
        tab('feed.html', 'feed', 'home', '新着') +
        tab('shops.html', 'shops', 'search', '探す') +
        '<a class="ulog-tab-add" href="shops.html" aria-label="うどんを記録する">' +
        '<i class="ti ti-plus"></i></a>' +
        tab('mypage.html', 'mypage', 'user', 'マイページ');
    } else {
      bar.innerHTML =
        tab('feed.html', 'feed', 'home', '新着') +
        tab('shops.html', 'shops', 'search', '探す') +
        tab('login.html', 'login', 'login', 'ログイン') +
        tab('register.html', 'register', 'user-plus', '登録');
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
