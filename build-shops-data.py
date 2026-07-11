#!/usr/bin/env python3
# ===========================================================================
# shops.csv → shops-data.js 変換スクリプト
# ---------------------------------------------------------------------------
# 使い方（udonlogフォルダで実行）:
#   python3 build-shops-data.py
#
# shops.csv を編集してこのスクリプトを実行すると、shops-data.js が
# 自動で作り直されます。店舗の追加・削除・修正は shops.csv の表で行えます。
#
# CSVの列:
#   id     … 一意の番号（重複・空欄NG。新店は最大値+1）
#   name   … 店名（必須）
#   city   … 市町（必須）
#   type   … セルフ / 一般 / 製麺所 のいずれか（必須）
#   dish   … 代表メニュー（任意）
#   kana   … よみ（あいうえお順の並べ替え用。ひらがな。例：手打ちうどん いわせ → いわせ）
#   hours  … 営業時間（任意。空欄なら詳細ページで「店舗にご確認ください」）
#   closed … 定休日（任意。同上）
#   status … 営業状態（空欄＝営業中 / 「閉店」＝閉店）。
#            「閉店」にすると一覧から非表示になり、エリア達成率の分母からも除外される。
#            idは消さず残すので、過去の記録・スタンプはそのまま保持される。
#   blog   … ブログ記事のURL（任意）。入れると店舗詳細に「ブログで詳しく読む」ボタンが出る。
#            空欄ならボタンは表示されない。
#   parking … 駐車場（"あり" / "なし" / 空欄=不明）。店舗詳細の基本情報に表示。
#   lat/lng … 緯度・経度（任意）。「現在地から近い順」検索に使用。
#            geocode-shops.py（Google Geocoding）で自動取得して埋める。空欄なら
#            距離計算の対象外（近い順では末尾に回る）。手で直しても可。
# ===========================================================================

import csv
import sys
import os

CSV_FILE = 'shops.csv'
OUT_FILE = 'shops-data.js'
VALID_TYPES = {'セルフ', '一般', '製麺所'}

HEADER = '''// ===========================================================================
// うどログ 店舗マスタデータ（全ページ共通の唯一のデータ元）
// ---------------------------------------------------------------------------
// このファイルは build-shops-data.py によって shops.csv から自動生成されます。
// 直接編集せず、shops.csv を編集して `python3 build-shops-data.py` を実行して
// ください。手で書き換えても、次回の生成で上書きされます。
// ===========================================================================

const SHOPS = [
'''

FOOTER = '];\n'


def esc(s):
    """JS文字列用に " をエスケープ"""
    return s.replace('\\', '\\\\').replace('"', '\\"')


def main():
    if not os.path.exists(CSV_FILE):
        print(f'エラー: {CSV_FILE} が見つかりません。')
        sys.exit(1)

    rows = []
    seen_ids = set()
    errors = []

    with open(CSV_FILE, encoding='utf-8-sig', newline='') as f:
        reader = csv.DictReader(f)
        for i, row in enumerate(reader, start=2):  # 2行目＝最初のデータ行
            name = (row.get('name') or '').strip()
            if not name:
                continue  # 空行はスキップ

            id_raw = (row.get('id') or '').strip()
            kana = (row.get('kana') or '').strip()
            city = (row.get('city') or '').strip()
            type_ = (row.get('type') or '').strip()
            dish = (row.get('dish') or '').strip()
            hours = (row.get('hours') or '').strip()
            closed = (row.get('closed') or '').strip()
            status = (row.get('status') or '').strip()
            blog = (row.get('blog') or '').strip()
            parking = (row.get('parking') or '').strip()
            slug = (row.get('slug') or '').strip()
            lat_raw = (row.get('lat') or '').strip()
            lng_raw = (row.get('lng') or '').strip()

            # 検証
            if not id_raw.isdigit():
                errors.append(f'{i}行目「{name}」: id が数字ではありません（{id_raw!r}）')
                continue
            id_ = int(id_raw)
            if id_ in seen_ids:
                errors.append(f'{i}行目「{name}」: id {id_} が重複しています')
                continue
            seen_ids.add(id_)
            if not city:
                errors.append(f'{i}行目「{name}」: city が空です')
            if type_ not in VALID_TYPES:
                errors.append(f'{i}行目「{name}」: type が不正です（{type_!r}）→ セルフ/一般/製麺所 のいずれか')

            # 緯度・経度（両方そろっていて数値の時だけ採用）
            lat = lng = None
            if lat_raw or lng_raw:
                try:
                    lat, lng = float(lat_raw), float(lng_raw)
                except ValueError:
                    errors.append(f'{i}行目「{name}」: lat/lng が数値ではありません（{lat_raw!r},{lng_raw!r}）')

            rows.append({
                'id': id_, 'name': name, 'kana': kana, 'city': city, 'type': type_,
                'dish': dish, 'hours': hours, 'closed': closed, 'status': status,
                'blog': blog, 'parking': parking, 'slug': slug,
                'lat': lat, 'lng': lng,
            })

    if errors:
        print('⚠️  エラーが見つかりました。修正してください:')
        for e in errors:
            print('  - ' + e)
        print('\n生成を中止しました。')
        sys.exit(1)

    # id順に並べる
    rows.sort(key=lambda r: r['id'])

    lines = []
    for r in rows:
        parts = [
            f'id:{r["id"]}',
            f'name:"{esc(r["name"])}"',
            f'kana:"{esc(r["kana"])}"',
            f'city:"{esc(r["city"])}"',
            f'type:"{esc(r["type"])}"',
            f'dish:"{esc(r["dish"])}"',
        ]
        if r['hours']:
            parts.append(f'hours:"{esc(r["hours"])}"')
        if r['closed']:
            parts.append(f'closed:"{esc(r["closed"])}"')
        if r['status']:
            parts.append(f'status:"{esc(r["status"])}"')
        if r['blog']:
            parts.append(f'blog:"{esc(r["blog"])}"')
        if r['parking']:
            parts.append(f'parking:"{esc(r["parking"])}"')
        if r['slug']:
            parts.append(f'slug:"{esc(r["slug"])}"')
        if r['lat'] is not None and r['lng'] is not None:
            parts.append(f'lat:{r["lat"]:.6f}')
            parts.append(f'lng:{r["lng"]:.6f}')
        lines.append('  { ' + ', '.join(parts) + ' },')

    with open(OUT_FILE, 'w', encoding='utf-8') as f:
        f.write(HEADER)
        f.write('\n'.join(lines) + '\n')
        f.write(FOOTER)

    print(f'✅ {OUT_FILE} を生成しました（{len(rows)}店）')


if __name__ == '__main__':
    main()
