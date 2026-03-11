"""
URLから情報をスクレイピングしてYouTube用に整形し、プレビュー表示するスクリプト
（実際の更新は行いません）

必要なライブラリ:
pip install beautifulsoup4 requests
"""

import re
import json
import requests
from bs4 import BeautifulSoup

def scrape_building_info(url):
    """URLから建築情報をスクレイピング"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        }
        response = requests.get(url, headers=headers, timeout=10)
        response.raise_for_status()
        
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # メタ情報を取得
        info = {}
        
        # タイトル
        og_title = soup.find('meta', property='og:title')
        title_tag = soup.find('title')
        info['raw_title'] = og_title['content'] if og_title else (title_tag.string if title_tag else '')
        
        # 説明
        description_meta = soup.find('meta', {'name': 'description'})
        info['description'] = description_meta['content'] if description_meta else ''
        
        # キーワード
        keywords_meta = soup.find('meta', {'name': 'keywords'})
        info['keywords'] = keywords_meta['content'] if keywords_meta else ''
        
        # 画像URL
        og_image = soup.find('meta', property='og:image')
        info['image_url'] = og_image['content'] if og_image else ''
        
        # 都道府県を取得（prefecture-badgeクラスから）
        prefecture_tag = soup.find('a', class_='prefecture-badge')
        if prefecture_tag:
            info['prefecture'] = prefecture_tag.get_text(strip=True)
        else:
            info['prefecture'] = ''
        
        # 用途を取得（building-type-badgeクラスから）
        building_type_tag = soup.find('a', class_='building-type-badge')
        if building_type_tag:
            info['building_type'] = building_type_tag.get_text(strip=True)
        else:
            info['building_type'] = ''
        
        # 英語タイトルを取得（building-cardのdata-title-en属性から）
        building_card = soup.find('div', class_='building-card')
        if building_card and building_card.get('data-title-en'):
            info['title_en'] = building_card.get('data-title-en')
        else:
            info['title_en'] = ''
        
        # サムネイル用の画像URLを取得して変換
        # まずIMGタグからsrc属性を取得
        img_tag = soup.find('img')
        if img_tag and img_tag.get('src'):
            original_url = img_tag.get('src')
            # サムネイルURLに変換
            # 例: /pictures/SK_2005_03_100-0/SK_2005_03_100-0_20250730_1414.webp
            # → https://kenchikuka.com/pictures/SK_2005_03_100-0/thumbs/SK_2005_03_100-0_20250730_1414_thumb.webp
            if '/pictures/' in original_url:
                parts = original_url.rsplit('/', 1)
                if len(parts) == 2:
                    directory = parts[0]
                    filename = parts[1]
                    # ファイル名と拡張子を分離
                    name_parts = filename.rsplit('.', 1)
                    if len(name_parts) == 2:
                        name = name_parts[0]
                        ext = name_parts[1]
                        # サムネイルURLを構築
                        thumbnail_url = f"{directory}/thumbs/{name}_thumb.{ext}"
                        # ドメインを追加
                        if not thumbnail_url.startswith('http'):
                            thumbnail_url = f"https://kenchikuka.com{thumbnail_url}"
                        info['thumbnail_url'] = thumbnail_url
                    else:
                        info['thumbnail_url'] = original_url if original_url.startswith('http') else f"https://kenchikuka.com{original_url}"
                else:
                    info['thumbnail_url'] = original_url if original_url.startswith('http') else f"https://kenchikuka.com{original_url}"
            else:
                info['thumbnail_url'] = original_url if original_url.startswith('http') else f"https://kenchikuka.com{original_url}"
        else:
            info['thumbnail_url'] = ''
        
        # JSON-LD構造化データを取得
        json_ld_script = soup.find('script', type='application/ld+json')
        if json_ld_script:
            try:
                json_data = json.loads(json_ld_script.string)
                info['structured_data'] = json_data
            except:
                info['structured_data'] = {}
        else:
            info['structured_data'] = {}
        
        return info
        
    except Exception as e:
        print(f"スクレイピングエラー: {e}")
        return None

def format_youtube_info(scraped_info, source_url):
    """スクレイピングした情報をYouTube用にフォーマット"""
    
    # 構造化データから詳細情報を取得
    structured = scraped_info.get('structured_data', {})
    building_name = structured.get('name', '')
    address = structured.get('address', {}).get('addressLocality', '')
    date_completed = structured.get('dateCompleted', '')
    architect = structured.get('architect', {}).get('name', '') if isinstance(structured.get('architect'), dict) else ''
    
    # 日本語タイトルを取得
    japanese_title = building_name if building_name else scraped_info['raw_title'].split('|')[0].strip()
    
    # 英語タイトルを取得（building-cardのdata-title-en属性から）
    english_title = scraped_info.get('title_en', '')
    
    # 都道府県を取得
    prefecture = scraped_info.get('prefecture', '')
    
    # タイトルを生成（最大100文字）
    title = f"{japanese_title} | {english_title} | {prefecture} #Shorts"
    title = title[:100]
    
    # 用途を取得
    building_type = scraped_info.get('building_type', '建築物')
    
    # 説明文を作成（最大5000文字）
    description_parts = []
    
    if building_name:
        description_parts.append(f"{building_name}の建築紹介動画です。\n")
    
    description_parts.append("■ 建物概要")
    if address:
        description_parts.append(f"📍 所在地：{address}")
    if architect:
        description_parts.append(f"🏗️ 設計：{architect}")
    if date_completed:
        year = date_completed.split('-')[0]
        description_parts.append(f"📅 完成年：{year}年")
    
    description_parts.append(f"🏛️ 用途：{building_type}")
    
    description_parts.append(f"\n■ 詳細情報")
    description_parts.append(f"建築の詳細情報、写真、地図はこちら：")
    description_parts.append(source_url)
    
    # キーワードからタグを生成
    keywords_str = scraped_info.get('keywords', '')
    keywords_list = [k.strip() for k in keywords_str.split(',') if k.strip()]
    
    # 一般的なタグを追加
    common_tags = ['建築', '日本建築', '建築デザイン', '建築巡り']
    
    if keywords_list:
        description_parts.append(f"\n■ 関連情報")
        hashtags = ' '.join([f"#{tag}" for tag in keywords_list[:8]])
        description_parts.append(hashtags)
    
    description_parts.append("\n---")
    description_parts.append("建築情報サイト PocketNavi")
    description_parts.append("🌐 https://kenchikuka.com")
    
    description = '\n'.join(description_parts)[:5000]
    
    # タグを生成（最大500文字、最大30個）
    tags = []
    for kw in keywords_list:
        if len(kw) <= 30 and len(tags) < 30:  # 各タグは30文字以内
            tags.append(kw)
    
    for tag in common_tags:
        if tag not in tags and len(tags) < 30:
            tags.append(tag)
    
    # カテゴリーIDを決定（建築関連は28: Science & Technology が適切）
    category_id = "28"
    
    # サムネイルURLを取得（変換済み）
    thumbnail_url = scraped_info.get('thumbnail_url', '')
    
    return {
        'title': title,
        'description': description,
        'tags': tags,
        'category_id': category_id,
        'thumbnail_url': thumbnail_url
    }

def print_preview(video_info):
    """整形された情報を見やすく表示"""
    print("\n" + "=" * 80)
    print("📺 YouTube動画情報プレビュー")
    print("=" * 80)
    
    print("\n【タイトル】")
    print(f"{video_info['title']}")
    print(f"（文字数: {len(video_info['title'])}文字 / 最大100文字）")
    
    print("\n【説明文】")
    print("-" * 80)
    print(video_info['description'])
    print("-" * 80)
    print(f"（文字数: {len(video_info['description'])}文字 / 最大5000文字）")
    
    print("\n【タグ】")
    print(f"タグ数: {len(video_info['tags'])}個 / 最大30個")
    for i, tag in enumerate(video_info['tags'], 1):
        print(f"  {i:2d}. {tag}")
    
    print("\n【カテゴリー】")
    category_names = {
        "22": "People & Blogs",
        "28": "Science & Technology"
    }
    category_name = category_names.get(video_info['category_id'], "不明")
    print(f"カテゴリーID: {video_info['category_id']} ({category_name})")
    
    print("\n【サムネイル画像URL】")
    print(video_info['thumbnail_url'])
    
    print("\n" + "=" * 80)
    print("✅ プレビュー完了")
    print("=" * 80)

def main():
    # ========== 設定項目 ==========
    SOURCE_URL = 'https://kenchikuka.com/buildings/central-japan-international-airport-passenger-terminal-building?lang=ja'
    # ==============================
    
    print("YouTube動画情報を整形してプレビュー表示します...")
    print(f"情報元URL: {SOURCE_URL}")
    print("-" * 80)
    
    # URLから情報をスクレイピング
    print("\n📡 ウェブサイトから情報を取得中...")
    scraped_info = scrape_building_info(SOURCE_URL)
    
    if not scraped_info:
        print("❌ エラー: 情報の取得に失敗しました")
        return
    
    print("✅ 情報の取得に成功しました")
    
    # YouTube用に情報をフォーマット
    print("\n🔧 YouTube用に情報を整形中...")
    video_info = format_youtube_info(scraped_info, SOURCE_URL)
    
    # プレビュー表示
    print_preview(video_info)
    
    print("\n💡 この情報で良ければ、実際に更新するスクリプトを実行してください。")

if __name__ == '__main__':
    main()