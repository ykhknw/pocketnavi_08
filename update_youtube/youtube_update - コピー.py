"""
YouTube Data API v3を使って動画のタイトルを変更するPythonスクリプト

事前準備:
1. Google Cloud Consoleでプロジェクトを作成
2. YouTube Data API v3を有効化
3. OAuth 2.0クライアントIDを作成（デスクトップアプリ）
4. credentials.jsonをダウンロードして同じディレクトリに配置
5. 必要なライブラリをインストール:
   pip install google-auth google-auth-oauthlib google-auth-httplib2 google-api-python-client
"""

import os
import google.auth
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

# 必要な権限スコープ
SCOPES = ['https://www.googleapis.com/auth/youtube.force-ssl']

def get_authenticated_service():
    """YouTube APIの認証済みサービスを取得"""
    creds = None
    
    # token.jsonがあれば認証情報を読み込む
    if os.path.exists('token.json'):
        creds = Credentials.from_authorized_user_file('token.json', SCOPES)
    
    # 認証情報が無効または存在しない場合
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        else:
            # 初回認証フロー
            flow = InstalledAppFlow.from_client_secrets_file(
                'credentials.json', SCOPES)
            creds = flow.run_local_server(port=0)
        
        # 認証情報を保存
        with open('token.json', 'w') as token:
            token.write(creds.to_json())
    
    return build('youtube', 'v3', credentials=creds)

def update_video_title(youtube, video_id, new_title):
    """動画のタイトルを変更"""
    try:
        # まず現在の動画情報を取得
        video_response = youtube.videos().list(
            part='snippet',
            id=video_id
        ).execute()
        
        if not video_response['items']:
            print(f"エラー: 動画ID {video_id} が見つかりません")
            return False
        
        # 現在の情報を取得
        video = video_response['items'][0]
        snippet = video['snippet']
        
        print(f"現在のタイトル: {snippet['title']}")
        
        # タイトルを更新
        snippet['title'] = new_title
        
        # 動画情報を更新
        update_response = youtube.videos().update(
            part='snippet',
            body={
                'id': video_id,
                'snippet': snippet
            }
        ).execute()
        
        print(f"新しいタイトル: {update_response['snippet']['title']}")
        print("タイトルの変更に成功しました！")
        return True
        
    except HttpError as e:
        print(f"APIエラーが発生しました: {e}")
        return False

def main():
    # 動画IDとタイトルを設定
    VIDEO_ID = 'YgRkJRkU0tU'  # https://youtube.com/shorts/YgRkJRkU0tU
    NEW_TITLE = 'タイトル更新テスト'
    
    print("YouTube Data API v3で動画タイトルを変更します...")
    print(f"動画ID: {VIDEO_ID}")
    print(f"新しいタイトル: {NEW_TITLE}")
    print("-" * 50)
    
    # 認証とサービス取得
    youtube = get_authenticated_service()
    
    # タイトル変更
    update_video_title(youtube, VIDEO_ID, NEW_TITLE)

if __name__ == '__main__':
    main()
