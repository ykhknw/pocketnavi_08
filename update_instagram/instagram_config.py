"""
Instagram API 設定ファイル
"""

# ============================================================
# Instagram API 認証情報
# ============================================================

INSTAGRAM_CONFIG = {
    # ページアクセストークン（期限切れの場合は更新が必要）
    "page_access_token": "EAFnDeowDCvwBP3KHZA7uqXDlIIXQxeTwqLJ1SfXIA8nkUoO0LDPaV0G9ZAIF6WsPKB4UZAS7BHQ32NXb0tNqUpLBJpxAvaxU5C7IzLGZCPAMf2sEuaLZBLAw3zSDBqAiQ55ZAVAdtpJCtguzLlJayZCZAH1GxWZB7DMQiNtaInZAi5wOXkAIFXIoZBqxdQZAJ3hL",
    
    # Instagram Business Account ID
    "instagram_account_id": "17841447613733018",
    
    # Facebook App ID
    "app_id": "252662040296844476",
    
    # Graph API バージョン
    "api_version": "v24.0",
}

# ============================================================
# 動画要件（Instagram規定）
# ============================================================

VIDEO_REQUIREMENTS = {
    # 動画の長さ
    "min_duration": 3,          # 最小3秒
    "max_duration": 60,         # 最大60秒（フィード投稿）
    "max_duration_reels": 90,   # 最大90秒（リール）
    
    # ファイルサイズ
    "max_filesize": 100 * 1024 * 1024,  # 100MB
    
    # 対応フォーマット
    "formats": [".mp4", ".mov"],
    
    # 推奨解像度
    "recommended_resolution": {
        "width": 1080,
        "height": 1080
    },
    
    # アスペクト比
    "aspect_ratio": {
        "min": 0.8,   # 4:5 (縦型)
        "max": 1.91   # 1.91:1 (横型)
    }
}

# ============================================================
# キャプション設定
# ============================================================

CAPTION_CONFIG = {
    # キャプション最大文字数
    "max_length": 2200,
    
    # ハッシュタグ最大数
    "max_hashtags": 30,
    
    # デフォルトスタイル
    "default_style": "youtube_style",  # "youtube_style" または "short_style"
    
    # 共通ハッシュタグ
    "common_hashtags": [
        "建築", "日本建築", "建築デザイン", "建築巡り",
        "architecture", "japan", "design", "PocketNavi"
    ],
    
    # ブランディング情報
    "branding": {
        "name": "PocketNavi",
        "description": "建築物検索データベース",
        "website": "https://kenchikuka.com"
    },

    # 音楽クレジット（追加）
    'music_credit': {
        'enabled': True,
        'title': '🎵使用楽曲',
        'bgm': 'BGM: MusMus',
        'credit': 'フリーBGM・音楽素材MusMus',
        'url': 'https://musmus.main.jp'
    }
}

# ============================================================
# 動画ホスティング設定（一時ウェブサーバー用）
# ============================================================

WEB_SERVER = {
    # 一時サーバーを使用するか
    "enabled": True,
    
    # ホスト設定
    "host": "0.0.0.0",
    
    # ポート番号
    "port": 8000,
    
    # パブリックURL（ngrokを使用する場合は後で設定）
    # 例: "https://xxxx-xx-xxx-xxx-xxx.ngrok-free.app"
    "public_url": None,
    
    # タイムアウト（秒）
    "timeout": 600  # 10分
}

# ============================================================
# API設定
# ============================================================

API_CONFIG = {
    # Graph API ベースURL
    "base_url": f"https://graph.facebook.com/{INSTAGRAM_CONFIG['api_version']}",
    
    # リトライ設定
    "max_retries": 3,
    "retry_delay": 5,  # 秒
    
    # ステータス確認
    "status_check": {
        "max_attempts": 60,   # 最大試行回数
        "interval": 10        # 確認間隔（秒）
    }
}

# ============================================================
# パス設定
# ============================================================

PATHS = {
    # 動画ディレクトリ
    "movies_dir": "D:/homepage/kenchikuka.com_new/movies",
    
    # 一時ファイルディレクトリ
    "temp_dir": "temp",
    
    # ログディレクトリ
    "log_dir": "logs"
}

# ============================================================
# デバッグ設定
# ============================================================

DEBUG = {
    # 詳細ログを出力
    "verbose": True,
    
    # テストモード（実際に投稿しない）
    "test_mode": False
}