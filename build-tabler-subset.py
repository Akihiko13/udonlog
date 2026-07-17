#!/usr/bin/env python3
# ===========================================================================
# うどログ Tablerアイコン サブセット生成スクリプト
# ---------------------------------------------------------------------------
# 全ページで使っているアイコン（class="ti ti-xxx"）だけを含む軽量フォントと
# CSSを生成します。CDNの全アイコン版（フォント820KB＋CSS 203KB）の代わりに
# 自前ホストの約1/50のファイルを読み込むため、表示が大幅に速くなります。
#
# 使い方（新しい ti-* アイコンをページに追加したら再実行するだけ）:
#   python3 build-tabler-subset.py
# 生成物（両方アップロードする）:
#   tabler-icons.css              → public_html/ 直下
#   fonts/tabler-icons-subset.woff2 → public_html/fonts/
#
# 必要ライブラリ: pip3 install --user fonttools brotli
# ===========================================================================
import io, os, re, sys, urllib.request

VERSION = "3.44.0"  # Tabler Icons のバージョン（固定）
CDN = f"https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@{VERSION}/dist"
HERE = os.path.dirname(os.path.abspath(__file__))

# --- 1. HTML/JS から使用アイコンを収集 -------------------------------------
# 「ti-xxx」の表記をすべて拾う（class="ti ti-xxx" も icon:'ti-xxx' も対象）。
# JSでクラス名を変数から組み立てていて「ti-」が現れない場合はここに手で追加する。
EXTRA = {
    "home", "user", "login",  # header.js の下部タブバー tab('...', '...', 'home', ...) 等
}
used = set(EXTRA)
for fn in os.listdir(HERE):
    if fn.endswith((".html", ".js")) and fn not in ("shops-data.js",):
        with open(os.path.join(HERE, fn), encoding="utf-8") as f:
            used.update(re.findall(r"\bti-([a-z0-9][a-z0-9-]*)", f.read()))
if not used:
    sys.exit("アイコンが見つかりません（*.html / *.js を確認してください）")
print(f"使用アイコン: {len(used)}種類")

# --- 2. 公式CSSから各アイコンのコードポイントを取得 -------------------------
# 通常アイコンは outline フォント。「ti-xxx-filled」は Tabler 3.x で別フォント
# （tabler-icons-filled・クラス名は ti-xxx）に分離されたので、そちらから引く。
def fetch(url):
    with urllib.request.urlopen(url) as r:
        return r.read()

def parse_css(css_text):
    return dict(re.findall(r"\.ti-([a-z0-9-]+):before\{content:\"\\([0-9a-f]+)\"\}", css_text))

outline_map = parse_css(fetch(f"{CDN}/tabler-icons.min.css").decode("utf-8"))
filled_map = parse_css(fetch(f"{CDN}/tabler-icons-filled.min.css").decode("utf-8"))

cp = {}         # 通常: name -> コードポイント（16進文字列）
cp_filled = {}  # filled: サイト上のクラス名（ti-xxx-filled）-> コードポイント
missing = []
for name in used:
    if name in outline_map:
        cp[name] = outline_map[name]
    elif name.endswith("-filled") and name[:-7] in filled_map:
        cp_filled[name] = filled_map[name[:-7]]
    else:
        missing.append(name)
if missing:
    print(f"⚠ 公式に存在しないアイコン（無視します）: {sorted(missing)}")

# --- 3. フォントをサブセット化（使用グリフのみ抽出） ------------------------
from fontTools import subset

def subset_font(font_bytes, codes, out_path):
    opts = subset.Options(flavor="woff2", hinting=False, desubroutinize=True)
    ss = subset.Subsetter(opts)
    ss.populate(unicodes=[int(c, 16) for c in codes])
    font = subset.load_font(io.BytesIO(font_bytes), opts)
    ss.subset(font)
    font.save(out_path)

os.makedirs(os.path.join(HERE, "fonts"), exist_ok=True)
out_font = os.path.join(HERE, "fonts", "tabler-icons-subset.woff2")
subset_font(fetch(f"{CDN}/fonts/tabler-icons.woff2"), cp.values(), out_font)
out_font_f = os.path.join(HERE, "fonts", "tabler-icons-filled-subset.woff2")
if cp_filled:
    subset_font(fetch(f"{CDN}/fonts/tabler-icons-filled.woff2"), cp_filled.values(), out_font_f)

# フォント内容のハッシュ。?v= に付けてキャッシュを破棄する
# （アイコンを追加してサブセットを作り直すとURLが自動で変わり、
#  ブラウザが古いフォントをキャッシュしたまま新グリフが欠ける事故を防ぐ）
def content_hash(path):
    import hashlib
    with open(path, "rb") as fp:
        return hashlib.md5(fp.read()).hexdigest()[:8]
hash_outline = content_hash(out_font)
hash_filled = content_hash(out_font_f) if cp_filled else ""

# --- 4. サブセットCSSを生成 -------------------------------------------------
# font-display:block … フォント読込中に豆腐文字を出さない（アイコンは代替不能なため）
n = len(cp) + len(cp_filled)
css = [
    f"/* Tabler Icons {VERSION} サブセット版（{n}種類・build-tabler-subset.py で自動生成） */",
    '@font-face{font-family:"tabler-icons";font-style:normal;font-weight:400;font-display:block;'
    f'src:url("fonts/tabler-icons-subset.woff2?v={VERSION}.{hash_outline}") format("woff2")}}',
    '.ti{font-family:"tabler-icons" !important;speak:none;font-style:normal;font-weight:normal;'
    "font-variant:normal;text-transform:none;line-height:1;-webkit-font-smoothing:antialiased;"
    "-moz-osx-font-smoothing:grayscale}",
]
for name in sorted(cp):
    css.append(f'.ti-{name}:before{{content:"\\{cp[name]}"}}')
if cp_filled:
    css.append(
        '@font-face{font-family:"tabler-icons-filled";font-style:normal;font-weight:400;font-display:block;'
        f'src:url("fonts/tabler-icons-filled-subset.woff2?v={VERSION}.{hash_filled}") format("woff2")}}'
    )
    for name in sorted(cp_filled):
        css.append(
            f'.ti-{name}{{font-family:"tabler-icons-filled" !important}}'
            f'.ti-{name}:before{{content:"\\{cp_filled[name]}"}}'
        )
out_css = os.path.join(HERE, "tabler-icons.css")
with open(out_css, "w", encoding="utf-8") as f:
    f.write("\n".join(css) + "\n")

print(f"生成: fonts/tabler-icons-subset.woff2 ({os.path.getsize(out_font):,} bytes)")
if cp_filled:
    print(f"生成: fonts/tabler-icons-filled-subset.woff2 ({os.path.getsize(out_font_f):,} bytes)")
print(f"生成: tabler-icons.css ({os.path.getsize(out_css):,} bytes)")
