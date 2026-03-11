"""
Instagram 建築動画自動アップロードツール
メインスクリプト
"""
# https://claude.ai/chat/9013a2e0-f85b-44c3-a60d-680e10dca38c より、
# 1.https://developers.facebook.com/tools/explorer/ を開く
# 2.アプリを選択：PocketNavi API
# 3.右欄「アクセストークン」のGenerate Access Tokenをクリック
# 4.上記を、「instagram_config.py」ファイルの「page_access_token」に代入する
# 5.上記は短期トークンなので、D:\homepage\kenchikuka.com_new\update_instagram\token_refresher.pyを使うと、長期トークンを取得できる



import os
import sys
from building_scraper import scrape_building_info, validate_url
from caption_formatter import format_instagram_caption, print_caption_preview
from video_validator import validate_video_file, get_video_info, print_video_info
from instagram_api import InstagramAPI
from instagram_config import INSTAGRAM_CONFIG

def print_header():
    """ヘッダー表示"""
    print("\n" + "=" * 80)
    print("Instagram 建築動画 自動アップロードツール")
    print("=" * 80)

def get_video_path():
    """動画パスを入力"""
    print("\n【ステップ 1/2】動画ファイルのパスを入力してください")
    print("例: D:\\homepage\\kenchikuka.com_new\\movies\\movie_cynthia-yamanote.mp4")
    
    while True:
        video_path = input("\n動画パス: ").strip()
        
        if not video_path:
            print("❌ パスを入力してください")
            continue
        
        # 引用符を削除（ドラッグ&ドロップ対応）
        video_path = video_path.strip('"').strip("'")
        
        if not os.path.exists(video_path):
            print(f"❌ ファイルが見つかりません: {video_path}")
            retry = input("もう一度入力しますか？ (Y/N): ").strip().lower()
            if retry != 'y':
                return None
            continue
        
        return video_path

def validate_video(video_path):
    """動画を検証"""
    print("\n" + "=" * 80)
    print("🔍 動画ファイルを検証中...")
    print("=" * 80)
    
    is_valid, errors, warnings = validate_video_file(video_path)
    video_info = get_video_info(video_path)
    
    # 結果表示
    if video_info:
        print_video_info(video_info)
    
    # エラー表示
    if errors:
        print("\n❌ 検証エラー:")
        for error in errors:
            print(f"  - {error}")
    
    # 警告表示
    if warnings:
        print("\n⚠️  警告:")
        for warning in warnings:
            print(f"  - {warning}")
    
    # リール判定
    is_reels = False
    if video_info and video_info.get('duration'):
        duration = video_info['duration']
        if duration > 60 and duration <= 90:
            is_reels = True
            print("\n" + "=" * 80)
            print("📱 この動画はリールとして投稿されます")
            print(f"   （動画の長さ: {duration:.1f}秒）")
            print("=" * 80)
    
    return is_valid or is_reels, video_info, is_reels

def get_building_url():
    """建物URLを入力"""
    print("\n【ステップ 2/2】建物情報のURLを入力してください")
    print("例: https://kenchikuka.com/buildings/cynthia-yamanote?lang=ja")
    
    while True:
        building_url = input("\nURL: ").strip()
        
        if not building_url:
            print("❌ URLを入力してください")
            continue
        
        if not validate_url(building_url):
            print("❌ 無効なURLです（kenchikuka.com/buildings/... の形式が必要）")
            retry = input("もう一度入力しますか？ (Y/N): ").strip().lower()
            if retry != 'y':
                return None
            continue
        
        return building_url

def scrape_and_generate_caption(building_url):
    """スクレイピングとキャプション生成"""
    print("\n" + "=" * 80)
    print("📡 建物情報を取得中...")
    print("=" * 80)
    
    scraped_info = scrape_building_info(building_url)
    
    if not scraped_info:
        print("❌ 情報の取得に失敗しました")
        return None
    
    print("✅ 情報の取得に成功")
    
    # キャプション生成
    print("\n🔧 キャプションを生成中...")
    caption = format_instagram_caption(scraped_info, building_url)
    
    # プレビュー
    print_caption_preview(caption)
    
    return caption

def get_video_url():
    """動画の公開URLを取得"""
    print("\n" + "=" * 80)
    print("🌐 動画の公開URL設定")
    print("=" * 80)
    
    print("\nInstagram APIは公開アクセス可能なHTTPS URLが必要です。")
    print("\n以下のいずれかの方法で動画を公開してください:")
    print("  1. kenchikuka.com のサーバーにアップロード（推奨）")
    print("  2. ngrokで一時的に公開")
    print("  3. AWS S3などのクラウドストレージ")
    
    print("\n例:")
    print("  https://kenchikuka.com/temp/movie_cynthia-yamanote.mp4")
    print("  https://xxxx-xx-xxx.ngrok-free.app/movie_cynthia-yamanote.mp4")
    
    while True:
        video_url = input("\n動画の公開URL: ").strip()
        
        if not video_url:
            print("❌ URLを入力してください")
            continue
        
        if not video_url.startswith('https://'):
            print("⚠️  HTTPSで始まるURLを推奨します")
            confirm = input("このURLで続行しますか？ (Y/N): ").strip().lower()
            if confirm != 'y':
                continue
        
        return video_url

def confirm_upload(is_reels):
    """アップロード確認"""
    post_type = "リール" if is_reels else "フィード投稿"
    
    print("\n" + "=" * 80)
    print(f"⚠️  上記の内容でInstagram（{post_type}）に投稿します")
    print("=" * 80)
    
    while True:
        confirm = input("\n投稿を実行しますか？ (Y/N): ").strip().lower()
        if confirm in ['y', 'n']:
            return confirm == 'y'
        print("❌ Y または N を入力してください")

def upload_to_instagram(video_url, caption, is_reels):
    """Instagramにアップロード"""
    print("\n" + "=" * 80)
    print("🚀 アップロードを開始します")
    print("=" * 80)
    
    # API接続
    print("\n🔐 Instagram APIに接続中...")
    api = InstagramAPI()
    
    # 接続テスト
    if not api.test_connection():
        print("❌ API接続に失敗しました")
        print("instagram_config.py のトークンを確認してください")
        return False
    
    # アップロード
    success, media_id = api.upload_video(video_url, caption, is_reels)
    
    return success

def main():
    """メイン処理"""
    print_header()
    
    # ステップ1: 動画パス入力
    video_path = get_video_path()
    if not video_path:
        print("\n❌ 処理を中止しました")
        return
    
    # 動画検証
    can_upload, video_info, is_reels = validate_video(video_path)
    if not can_upload:
        print("\n❌ 動画がInstagram要件を満たしていません")
        print("動画を修正してから再試行してください")
        return
    
    # ステップ2: 建物URL入力
    building_url = get_building_url()
    if not building_url:
        print("\n❌ 処理を中止しました")
        return
    
    # スクレイピング＆キャプション生成
    caption = scrape_and_generate_caption(building_url)
    if not caption:
        print("\n❌ キャプションの生成に失敗しました")
        return
    
    # 確認
    if not confirm_upload(is_reels):
        print("\n❌ アップロードをキャンセルしました")
        return
    
    # 動画URL取得
    video_url = get_video_url()
    if not video_url:
        print("\n❌ 処理を中止しました")
        return
    
    # アップロード実行
    success = upload_to_instagram(video_url, caption, is_reels)
    
    # 結果
    if success:
        print("\n" + "=" * 80)
        print("🎉 すべての処理が完了しました！")
        print("=" * 80)
        print("\nInstagramアプリで投稿を確認してください:")
        print("https://www.instagram.com/pocket_navi/")
    else:
        print("\n" + "=" * 80)
        print("❌ アップロードに失敗しました")
        print("=" * 80)
        print("\nエラーメッセージを確認して、再試行してください")

if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n❌ ユーザーによって中断されました")
        sys.exit(1)
    except Exception as e:
        print(f"\n❌ 予期しないエラー: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)