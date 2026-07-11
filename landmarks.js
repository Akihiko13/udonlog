// ===========================================================================
// うどログ 観光地マスタ（「スポットから近い順」検索用）
// ---------------------------------------------------------------------------
// このファイルは build-landmarks.py によって landmarks.csv から自動生成されます。
// 直接編集せず、landmarks.csv を編集して `python3 build-landmarks.py` を実行して
// ください。座標(lat/lng)は Google Geocoding で取得します。
// ===========================================================================

const LANDMARKS = [
  { id:1, name:"栗林公園", kana:"りつりんこうえん", category:"観光" },
  { id:2, name:"金刀比羅宮", kana:"ことひらぐう", category:"観光" },
  { id:3, name:"丸亀城", kana:"まるがめじょう", category:"観光" },
  { id:4, name:"屋島", kana:"やしま", category:"観光" },
  { id:5, name:"父母ヶ浜", kana:"ちちぶがはま", category:"観光" },
  { id:6, name:"銭形砂絵", kana:"ぜにがたすなえ", category:"観光" },
  { id:7, name:"四国水族館", kana:"しこくすいぞくかん", category:"観光" },
  { id:8, name:"瀬戸大橋記念公園", kana:"せとおおはしきねんこうえん", category:"観光" },
  { id:9, name:"高松城跡・玉藻公園", kana:"たかまつじょうあとたまもこうえん", category:"観光" },
  { id:10, name:"総本山善通寺", kana:"そうほんざんぜんつうじ", category:"観光" },
  { id:11, name:"高屋神社", kana:"たかやじんじゃ", category:"観光" },
  { id:12, name:"国営讃岐まんのう公園", kana:"こくえいさぬきまんのうこうえん", category:"観光" },
  { id:13, name:"NEWレオマワールド", kana:"にゅーれおまわーるど", category:"観光" },
  { id:14, name:"大窪寺", kana:"おおくぼじ", category:"観光" },
  { id:15, name:"四国村ミウゼアム", kana:"しこくむらみうぜあむ", category:"観光" },
  { id:16, name:"ヤドン公園", kana:"やどんこうえん", category:"観光" },
  { id:17, name:"さぬきこどもの国", kana:"さぬきこどものくに", category:"観光" },
  { id:18, name:"津田の松原", kana:"つだのまつばら", category:"観光" },
  { id:19, name:"日本ドルフィンセンター", kana:"にほんどるふぃんせんたー", category:"観光" },
  { id:20, name:"旧金毘羅大芝居（金丸座）", kana:"きゅうこんぴらおおしばいかなまるざ", category:"観光" },
  { id:21, name:"丸亀市猪熊弦一郎現代美術館", kana:"まるがめしいのくまげんいちろうげんだいびじゅつかん", category:"観光" },
  { id:22, name:"香川県立東山魁夷せとうち美術館", kana:"かがわけんりつひがしやまかいいせとうちびじゅつかん", category:"観光" },
  { id:23, name:"豊稔池堰堤", kana:"ほうねんいけえんてい", category:"観光" },
  { id:24, name:"紫雲出山", kana:"しうでやま", category:"観光" },
  { id:25, name:"引田の古い町並み・讃州井筒屋敷", kana:"ひけたのふるいまちなみさんしゅういづつやしき", category:"観光" },
  { id:26, name:"高松港", kana:"たかまつこう", category:"港" },
  { id:27, name:"高松空港", kana:"たかまつくうこう", category:"空港" },
  { id:28, name:"高松駅", kana:"たかまつえき", category:"駅" },
  { id:29, name:"高松築港駅", kana:"たかまつちっこうえき", category:"駅" },
  { id:30, name:"瓦町駅", kana:"かわらまちえき", category:"駅" },
  { id:31, name:"栗林駅", kana:"りつりんえき", category:"駅" },
  { id:32, name:"仏生山駅", kana:"ぶっしょうざんえき", category:"駅" },
  { id:33, name:"琴電屋島駅", kana:"ことでんやしまえき", category:"駅" },
  { id:34, name:"坂出駅", kana:"さかいでえき", category:"駅" },
  { id:35, name:"宇多津駅", kana:"うたづえき", category:"駅" },
  { id:36, name:"丸亀駅", kana:"まるがめえき", category:"駅" },
  { id:37, name:"多度津駅", kana:"たどつえき", category:"駅" },
  { id:38, name:"善通寺駅", kana:"ぜんつうじえき", category:"駅" },
  { id:39, name:"琴平駅", kana:"ことひらえき", category:"駅" },
  { id:40, name:"琴電琴平駅", kana:"ことでんことひらえき", category:"駅" },
  { id:41, name:"観音寺駅", kana:"かんおんじえき", category:"駅" },
  { id:42, name:"志度駅", kana:"しどえき", category:"駅" },
  { id:43, name:"綾川駅", kana:"あやがわえき", category:"駅" },
];
