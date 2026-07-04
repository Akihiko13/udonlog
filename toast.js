// ===========================================================================
// うどログ 共通トースト（全ページ共通）
// ---------------------------------------------------------------------------
// <head> 内で <script src="toast.js"></script> を読み込むと、グローバル関数
//   showToast(メッセージ, { error:true, duration:2500 })
// が使えるようになります。無骨な alert() の置き換え用。
// 見た目は record.html / shop-detail.html の「下部ダークピル」に合わせています。
// ※ ページ側で独自の showToast を定義している場合は、そちらが優先されるよう
//   既に window.showToast があれば何もしません。
// ===========================================================================
(function () {
  if (window.showToast) return;   // ページ独自実装があれば尊重する

  var css =
    '.ulog-toast{position:fixed;left:50%;bottom:2rem;' +
    'transform:translateX(-50%) translateY(20px);background:#1a1a1a;color:#fff;' +
    'padding:12px 20px;border-radius:99px;font-size:14px;line-height:1.5;' +
    'max-width:90vw;text-align:center;opacity:0;pointer-events:none;' +
    'transition:opacity .3s,transform .3s;z-index:9999;' +
    "font-family:-apple-system,BlinkMacSystemFont,'Helvetica Neue',Arial,sans-serif;" +
    'box-shadow:0 6px 20px rgba(0,0,0,.25);}' +
    '.ulog-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}' +
    '.ulog-toast.err{background:#c0392b;}';

  var style = document.createElement('style');
  style.textContent = css;
  (document.head || document.documentElement).appendChild(style);

  var el = null;
  var timer = null;

  window.showToast = function (msg, opts) {
    opts = opts || {};
    if (!el) {
      el = document.createElement('div');
      el.className = 'ulog-toast';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.toggle('err', !!opts.error);
    // いったん非表示に戻してからアニメーションを再生（連続表示でも動くように）
    el.classList.remove('show');
    void el.offsetWidth;
    el.classList.add('show');
    clearTimeout(timer);
    timer = setTimeout(function () {
      el.classList.remove('show');
    }, opts.duration || 2800);
  };
})();
