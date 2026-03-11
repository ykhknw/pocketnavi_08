import os
import google_auth_oauthlib.flow
import googleapiclient.discovery
import googleapiclient.errors

# OAuth 2.0 クライアントシークレットファイルへのパス
CLIENT_SECRETS_FILE = 'client_secret.json'

# このスコープは、認証されたユーザーのYouTubeアカウントへのフルアクセスを許可します。
SCOPES = ['https://www.googleapis.com/auth/youtube']
API_SERVICE_NAME = 'youtube'
API_VERSION = 'v3'

def get_authenticated_service():
    """OAuth 2.0 フローを通じて認証済みサービスオブジェクトを取得します。"""
    flow = google_auth_oauthlib.flow.InstalledAppFlow.from_client_secrets_file(
        CLIENT_SECRETS_FILE, SCOPES)
    credentials = flow.run_local_server(port=0) # port=0 で空いているポートを自動選択
    return googleapiclient.discovery.build(
        API_SERVICE_NAME, API_VERSION, credentials=credentials)

def update_video_details(youtube, video_id, new_title, new_description=None, new_tags=None, new_category_id=None, new_privacy_status=None):
    """
    YouTube動画のメタデータを更新します。

    Args:
        youtube: 認証済みのYouTube APIサービスオブジェクト。
        video_id (str): 更新したい動画のID。
        new_title (str, optional): 新しい動画タイトル。Noneの場合は更新しない。
        new_description (str, optional): 新しい動画説明。Noneの場合は更新しない。
        new_tags (list, optional): 新しいタグのリスト。Noneの場合は更新しない。
        new_category_id (str, optional): 新しいカテゴリID。Noneの場合は更新しない。
        new_privacy_status (str, optional): 新しいプライバシー設定 (public, unlisted, private)。Noneの場合は更新しない。
    """
    try:
        # 現在の動画のメタデータを取得
        # 'snippet' part を取得して、既存の情報を保持しつつ更新する
        videos_list_response = youtube.videos().list(
            id=video_id,
            part='snippet,status' # snippet と status はよく更新する部分
        ).execute()

        if not videos_list_response['items']:
            print(f'エラー: 動画ID "{video_id}" が見つかりません。')
            return

        video_resource = videos_list_response['items'][0]
        
        # 更新するメタデータを設定
        if new_title is not None:
            video_resource['snippet']['title'] = new_title
        if new_description is not None:
            video_resource['snippet']['description'] = new_description
        if new_tags is not None:
            video_resource['snippet']['tags'] = new_tags
        if new_category_id is not None:
            video_resource['snippet']['categoryId'] = new_category_id
        if new_privacy_status is not None:
            video_resource['status']['privacyStatus'] = new_privacy_status

        # videos.update APIを呼び出して動画のメタデータを更新
        update_response = youtube.videos().update(
            part='snippet,status', # 更新する part を指定
            body=video_resource
        ).execute()

        print(f'動画ID "{video_id}" の詳細を更新しました。')
        print(f'新しいタイトル: {update_response["snippet"]["title"]}')
        if 'description' in update_response['snippet']:
            print(f'新しい説明: {update_response["snippet"]["description"]}')
        if 'tags' in update_response['snippet']:
            print(f'新しいタグ: {update_response["snippet"]["tags"]}')
        if 'status' in update_response and 'privacyStatus' in update_response['status']:
            print(f'新しいプライバシー設定: {update_response["status"]["privacyStatus"]}')


    except googleapiclient.errors.HttpError as e:
        print(f'APIエラーが発生しました: {e}')
        print(f'エラー詳細: {e.content.decode("utf-8")}')
    except Exception as e:
        print(f'予期せぬエラーが発生しました: {e}')

if __name__ == '__main__':
    # 認証
    youtube = get_authenticated_service()

    # 更新したい動画のIDを指定してください
    # 例: "YOUR_VIDEO_ID_HERE" (YouTubeの動画URLからv=の後に続く文字列)
#https://youtube.com/shorts/hRbmNm7z0Us
    target_video_id = 'hRbmNm7z0Us' # ここに実際のYouTube動画IDを入力
    # 更新したい詳細情報を設定
    # Noneにするとその項目は更新されません
#    new_video_title = 'Pythonで自動更新された動画のタイトル'
    new_video_title = 'にじのもりハウス NIJINOMORI HOUSE 2003　#shorts #'
#    new_video_description = 'これはPythonスクリプトによって自動的に更新された説明文です。APIを使ってさまざまな詳細を編集できます。'
    new_video_description = None
#    new_video_tags = ['Python', 'YouTube API', '自動化', 'プログラミング']
    new_video_tags = ['建築']
#    new_video_tags = None
    # YouTubeカテゴリIDの例:
    # 22: People & Blogs
    # 24: Entertainment
    # 28: Science & Technology
    new_video_category_id = '28' # 例: サイエンス＆テクノロジー
#    new_video_privacy_status = 'unlisted' # 'public', 'unlisted', 'private'
#    new_video_category_id = None # 例: サイエンス＆テクノロジー
    new_video_privacy_status = None # 'public', 'unlisted', 'private'

    update_video_details(
        youtube,
        target_video_id,
        new_title=new_video_title,
#        new_description=new_video_description,
        new_tags=new_video_tags,
        new_category_id=new_video_category_id,
#        new_privacy_status=new_video_privacy_status
    )

#    print("\n--- 動画の情報をさらに更新する例 ---")
#    # 例: タイトルだけを更新
#    update_video_details(
#        youtube,
#        target_video_id,
#        new_title="さらに新しいタイトル (Pythonで更新)",
#        new_description=None, # 説明は変更しない
#        new_tags=None
#    )
