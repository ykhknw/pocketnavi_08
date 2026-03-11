"""
YouTube Data API v3 + スクレイピングで動画情報を対話式で更新

事前準備:
pip install google-auth google-auth-oauthlib google-auth-httplib2 google-api-python-client beautifulsoup4 requests
"""

import os
import re
import json
import requests
from bs4 import BeautifulSoup
import google.auth
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError
from googleapiclient.http import MediaFileUpload

# 必要な権限スコープ
SCOPES = ['https://www.googleapis.com/auth/youtube.force-ssl']

def validate_youtube_url(url):
    """YouTube URLの妥当性をチェック"""
    patterns = [
        r'https?://(?:www\.)?youtube\.com/watch\?v=([a-zA-Z0-9_-]{11})',
        r'https?://(?:www\.)?youtube\.com/shorts/([a-zA-Z0-9_-]{11})',
        r'https?://youtu\.be/([a-zA-Z0-9_-]{11})'
    ]
    
    for pattern in patterns:
        match = re.match(pattern, url)
        if match:
            return match.group(1)
    return None

def validate_pocketnavi_url(url):
    """PocketNavi URLの妥当性をチェック"""
    if 'kenchikuka.com' in url and '/buildings/' in url:
        return True
    return False

def get_authenticated_service():
    """YouTube APIの認証済みサービスを取得"""
    creds = None
    
    if os.path.exists('token.json'):
        creds = Credentials.from_authorized_user_file('token.json', SCOPES)
    
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        else:
            flow = InstalledAppFlow.from_client_secrets_file(
                'credentials.json', SCOPES)
            creds = flow.run_local_server(port=0)
        
        with open('token.json', 'w') as token:
            token.write(creds.to_json())
    
    return build('youtube', 'v3', credentials=creds)

def scrape_building_info(url):
    """URLから建築情報をスクレイピング"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        }
        response = requests.get(url, headers=headers, timeout=10)
        response.raise_for_status()
        
        soup = BeautifulSoup(response.content, 'html.parser')
        
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
        
        # 都道府県を取得
        prefecture_tag = soup.find('a', class_='prefecture-badge')
        if prefecture_tag:
            info['prefecture'] = prefecture_tag.get_text(strip=True)
        else:
            info['prefecture'] = ''
        
        # 用途を取得
        building_type_tag = soup.find('a', class_='building-type-badge')
        if building_type_tag:
            info['building_type'] = building_type_tag.get_text(strip=True)
        else:
            info['building_type'] = ''
        
        # 英語タイトルを取得
        building_card = soup.find('div', class_='building-card')
        if building_card and building_card.get('data-title-en'):
            info['title_en'] = building_card.get('data-title-en')
        else:
            info['title_en'] = ''
        
        # サムネイル用の画像URLを取得して変換
        img_tag = soup.find('img')
        if img_tag and img_tag.get('src'):
            original_url = img_tag.get('src')
            if '/pictures/' in original_url:
                parts = original_url.rsplit('/', 1)
                if len(parts) == 2:
                    directory = parts[0]
                    filename = parts[1]
                    name_parts = filename.rsplit('.', 1)
                    if len(name_parts) == 2:
                        name = name_parts[0]
                        ext = name_parts[1]
                        thumbnail_url = f"{directory}/thumbs/{name}_thumb.{ext}"
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
        print(f"❌ スクレイピングエラー: {e}")
        return None

def format_youtube_info(scraped_info, source_url):
    """スクレイピングした情報をYouTube用にフォーマット"""
    
    structured = scraped_info.get('structured_data', {})
    building_name = structured.get('name', '')
    address = structured.get('address', {}).get('addressLocality', '')
    date_completed = structured.get('dateCompleted', '')
    architect = structured.get('architect', {}).get('name', '') if isinstance(structured.get('architect'), dict) else ''
    
    # 日本語タイトルを取得
    japanese_title = building_name if building_name else scraped_info['raw_title'].split('|')[0].strip()
    
    # 英語タイトルを取得
    english_title = scraped_info.get('title_en', '')
    
    # 都道府県を取得
    prefecture = scraped_info.get('prefecture', '')
    
    # タイトルを生成（最大100文字）
    title = f"{japanese_title} | {english_title} | {prefecture} #Shorts"
    title = title[:100]
    
    # 用途を取得
    building_type = scraped_info.get('building_type', '建築物')
    
    # 説明文を作成
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
    
    keywords_str = scraped_info.get('keywords', '')
    keywords_list = [k.strip() for k in keywords_str.split(',') if k.strip()]
    
    common_tags = ['建築', '日本建築', '建築デザイン', '建築巡り']
    
    if keywords_list:
        description_parts.append(f"\n■ 関連情報")
        hashtags = ' '.join([f"#{tag}" for tag in keywords_list[:8]])
        description_parts.append(hashtags)
    
    description_parts.append("\n---")
    description_parts.append("建築情報サイト PocketNavi")
    description_parts.append("🌐 https://kenchikuka.com")
    
    description = '\n'.join(description_parts)[:5000]
    
    # タグを生成
    tags = []
    for kw in keywords_list:
        if len(kw) <= 30 and len(tags) < 30:
            tags.append(kw)
    
    for tag in common_tags:
        if tag not in tags and len(tags) < 30:
            tags.append(tag)
    
    category_id = "27"  # Education
    
    thumbnail_url = scraped_info.get('thumbnail_url', '')
    
    return {
        'title': title,
        'description': description,
        'tags': tags,
        'category_id': category_id,
        'thumbnail_url': thumbnail_url
    }

def print_preview(video_info, is_shorts=False):
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
        "19": "Travel & Events",
        "27": "Education",
        "28": "Science & Technology"
    }
    category_name = category_names.get(video_info['category_id'], "不明")
    print(f"カテゴリーID: {video_info['category_id']} ({category_name})")
    
    print("\n【サムネイル画像URL】")
    if is_shorts:
        print("⚠️ この動画はShortsのため、サムネイルは更新されません")
        print("（Shortsのサムネイルは動画から自動生成されます）")
        print(f"参考URL: {video_info['thumbnail_url']}")
    else:
        print(video_info['thumbnail_url'])
        print("✅ このサムネイル画像で更新されます")
    
    print("\n" + "=" * 80)

def download_thumbnail(url, filename='thumbnail.jpg'):
    """サムネイル画像をダウンロード"""
    try:
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        with open(filename, 'wb') as f:
            f.write(response.content)
        print(f"✅ サムネイルをダウンロードしました: {filename}")
        return filename
    except Exception as e:
        print(f"❌ サムネイルのダウンロードエラー: {e}")
        return None

def update_video_info(youtube, video_id, video_info):
    """動画情報を更新"""
    try:
        video_response = youtube.videos().list(
            part='snippet,status',
            id=video_id
        ).execute()
        
        if not video_response['items']:
            print(f"❌ エラー: 動画ID {video_id} が見つかりません")
            return False
        
        video = video_response['items'][0]
        snippet = video['snippet']
        
        # 情報を更新
        snippet['title'] = video_info['title']
        snippet['description'] = video_info['description']
        snippet['tags'] = video_info['tags']
        snippet['categoryId'] = video_info['category_id']
        
        # 動画情報を更新
        youtube.videos().update(
            part='snippet',
            body={
                'id': video_id,
                'snippet': snippet
            }
        ).execute()
        
        print("\n✅ 動画情報の更新に成功しました！")
        return True
        
    except HttpError as e:
        print(f"❌ APIエラーが発生しました: {e}")
        return False

def update_thumbnail(youtube, video_id, thumbnail_path):
    """サムネイルを更新"""
    try:
        youtube.thumbnails().set(
            videoId=video_id,
            media_body=MediaFileUpload(thumbnail_path, mimetype='image/jpeg')
        ).execute()
        print("✅ サムネイルの更新に成功しました！")
        return True
    except HttpError as e:
        print(f"❌ サムネイル更新エラー: {e}")
        return False

def main():
    print("=" * 80)
    print("YouTube動画情報 自動更新ツール")
    print("=" * 80)
    
    # 1. YouTube動画URLの入力
    is_shorts = False
    while True:
        print("\n【ステップ 1/2】YouTube動画のURLを入力してください")
        print("例: https://youtube.com/shorts/YgRkJRkU0tU")
        youtube_url = input("YouTube URL: ").strip()
        
        video_id = validate_youtube_url(youtube_url)
        if video_id:
            print(f"✅ 動画ID: {video_id}")
            # Shortsかどうかを判定
            if '/shorts/' in youtube_url:
                is_shorts = True
                print("ℹ️  この動画はShortsです（サムネイルは更新できません）")
            break
        else:
            print("❌ 無効なYouTube URLです。もう一度入力してください。")
    
    # 2. PocketNavi URLの入力
    while True:
        print("\n【ステップ 2/2】PocketNaviのURLを入力してください")
        print("例: https://kenchikuka.com/buildings/central-japan-international-airport-passenger-terminal-building?lang=ja")
        pocketnavi_url = input("PocketNavi URL: ").strip()
        
        if validate_pocketnavi_url(pocketnavi_url):
            print(f"✅ URL確認完了")
            break
        else:
            print("❌ 無効なPocketNavi URLです。もう一度入力してください。")
    
    # 3. 情報を取得してプレビュー
    print("\n" + "-" * 80)
    print("📡 ウェブサイトから情報を取得中...")
    scraped_info = scrape_building_info(pocketnavi_url)
    
    if not scraped_info:
        print("❌ エラー: 情報の取得に失敗しました")
        return
    
    print("✅ 情報の取得に成功しました")
    
    print("\n🔧 YouTube用に情報を整形中...")
    video_info = format_youtube_info(scraped_info, pocketnavi_url)
    
    # プレビュー表示（Shorts情報を渡す）
    print_preview(video_info, is_shorts)
    
    # 4. 更新確認
    print("\n" + "=" * 80)
    print("⚠️  上記の内容でYouTube動画を更新します")
    print("=" * 80)
    
    # 5. 最終確認
    while True:
        confirm = input("\n更新を実行しますか？ (Y/y: 実行, N/n: キャンセル): ").strip().lower()
        if confirm in ['y', 'n']:
            break
        print("❌ Y/y または N/n を入力してください。")
    
    if confirm != 'y':
        print("\n❌ 更新をキャンセルしました。")
        return
    
    # 6. 実際に更新
    print("\n" + "=" * 80)
    print("🚀 更新を開始します...")
    print("=" * 80)
    
    print("\n🔐 YouTube APIに接続中...")
    youtube = get_authenticated_service()
    
    print("\n📝 動画情報を更新中...")
    success = update_video_info(youtube, video_id, video_info)
    
    # Shortsの場合はサムネイル更新をスキップ
    if is_shorts:
        print("\n⚠️ Shortsのため、サムネイル更新はスキップされました")
        print("（Shortsのサムネイルは動画から自動生成されます）")
    elif success and video_info['thumbnail_url']:
        print("\n🖼️ サムネイルを更新中...")
        thumbnail_path = download_thumbnail(video_info['thumbnail_url'])
        if thumbnail_path:
            update_thumbnail(youtube, video_id, thumbnail_path)
            if os.path.exists(thumbnail_path):
                os.remove(thumbnail_path)
    
    print("\n" + "=" * 80)
    print("🎉 すべての処理が完了しました！")
    print("=" * 80)

if __name__ == '__main__':
    main()