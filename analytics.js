// ===========================================================================
// うどログ Google Analytics（GA4）共通設定
// ---------------------------------------------------------------------------
// 測定IDを下の GA_ID に入れるだけで、読み込んでいる全ページで計測が有効になります。
// 各ページは <head> 内で <script src="analytics.js"></script> を読み込みます。
//   例: GA_ID = 'G-ABCDEFG123';
// ※ 未設定（プレースホルダのまま）の場合は何もしません。
// ===========================================================================
(function () {
  var GA_ID = 'G-2F7FV2T5WC';   // ← ここに発行された測定IDを入れてください

  // 未設定・プレースホルダ（X を含む）のときは計測を読み込まない
  if (!GA_ID || GA_ID.indexOf('G-') !== 0 || /X/.test(GA_ID)) return;

  // gtag.js を読み込み
  var s = document.createElement('script');
  s.async = true;
  s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
  document.head.appendChild(s);

  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', GA_ID);
})();