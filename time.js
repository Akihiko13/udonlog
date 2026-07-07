// ===========================================================================
// うどログ 相対時間ヘルパー（全ページ共通）
// ---------------------------------------------------------------------------
// 「たった今 / 3分前 / 5時間前 / 昨日 / 3日前」のような相対表記を作ります。
// 主に新着フィード・お店の投稿・コメント・通知など、交流系の“タイムライン”で使用。
//   window.ulogTimeAgo(dateStr)  … 相対表記（1週間以上前は日付表記に切替）
//   window.ulogDateFull(dateStr) … 「2026年7月7日 12:34」（ツールチップ用）
//   window.ulogDate(dateStr)     … 「7月7日」（同年）/「2025年7月7日」（別年）
// ※ 引数は MySQL の "YYYY-MM-DD HH:MM:SS" / "YYYY-MM-DD" 文字列や Date を想定。
// ===========================================================================
(function () {
  // MySQLのdatetime文字列は空白区切り。iPhone(Safari)は "T" 区切りでないと
  // パースに失敗するため、空白を "T" に置換してから Date にする。
  function parse(d) {
    if (!d) return null;
    if (d instanceof Date) return isNaN(d.getTime()) ? null : d;
    var dt = new Date(String(d).trim().replace(' ', 'T'));
    return isNaN(dt.getTime()) ? null : dt;
  }

  function two(n) { return (n < 10 ? '0' : '') + n; }

  // 絶対表記：同じ年なら「M月D日」、違う年は「YYYY年M月D日」
  function formatDate(d) {
    var dt = parse(d);
    if (!dt) return '';
    var md = (dt.getMonth() + 1) + '月' + dt.getDate() + '日';
    return dt.getFullYear() === new Date().getFullYear() ? md : (dt.getFullYear() + '年' + md);
  }

  // フル表記（ツールチップ用）：「YYYY年M月D日 HH:MM」
  function formatFull(d) {
    var dt = parse(d);
    if (!dt) return '';
    return dt.getFullYear() + '年' + (dt.getMonth() + 1) + '月' + dt.getDate() + '日 '
      + two(dt.getHours()) + ':' + two(dt.getMinutes());
  }

  // 相対表記：たった今 / N分前 / N時間前 / 昨日 / N日前 /（7日以上前は日付）
  function timeAgo(d) {
    var dt = parse(d);
    if (!dt) return '';
    var sec = Math.floor((Date.now() - dt.getTime()) / 1000);
    if (sec < 0) sec = 0;                       // 端末時計のズレで未来判定になった時の保険
    if (sec < 60) return 'たった今';
    var min = Math.floor(sec / 60);
    if (min < 60) return min + '分前';
    var hour = Math.floor(min / 60);
    if (hour < 24) return hour + '時間前';
    var day = Math.floor(hour / 24);
    if (day === 1) return '昨日';
    if (day < 7) return day + '日前';
    return formatDate(dt);                       // 1週間以上前は日付表記に切り替え
  }

  window.ulogTimeAgo = timeAgo;
  window.ulogDateFull = formatFull;
  window.ulogDate = formatDate;
})();
