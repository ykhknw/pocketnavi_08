"""
アクセストークンを長期トークンに変換
"""

import requests
import json
from datetime import datetime, timedelta

# 設定
APP_ID = "25266204029684476"  # あなたのアプリID
APP_SECRET = "eb9f3abd04ea270229a69236d70e0c07"  # ← アプリシークレットを入力
SHORT_LIVED_TOKEN = "EAFnDeowDCvwBP9Vg4pLqCeM8r8JHxlfgteaPf0oVUJ5uo6mjwEl5eMAfXsYIbYCOHUmomjok2vQK6T4YAc8263mMY0MqRZArlvIBjfAzmybNOuAhEBpWT0EBIjPHum4bNd6zoxUS3GpZC2d3ZAGzkQyl5wje9XbARbSigWaHM4Hn1wITzRgOtG9qLfAZCZACY8eZAFnlaOlmgWxVe1nrfY4N9XUvmqMo2dQ6s7kMpUJTVGJJbfVK8q0OplbOwOKjcTI5bsa9BZCZBboZCoopaqJIT"  # ← グラフAPIエクスプローラーのトークンを入力

def get_long_lived_token(app_id, app_secret, short_token):
    """
    短期トークンを長期トークン（60日）に変換
    """
    print("=" * 80)
    print("🔄 長期トークンの取得")
    print("=" * 80)
    
    url = "https://graph.facebook.com/v24.0/oauth/access_token"
    params = {
        "grant_type": "fb_exchange_token",
        "client_id": app_id,
        "client_secret": app_secret,
        "fb_exchange_token": short_token
    }
    
    print("\n📡 リクエスト送信中...")
    
    try:
        response = requests.get(url, params=params, timeout=10)
        result = response.json()
        
        if "access_token" in result:
            long_token = result["access_token"]
            expires_in = result.get("expires_in", 5184000)  # デフォルト60日
            
            # 有効期限を計算
            expiry_date = datetime.now() + timedelta(seconds=expires_in)
            days = expires_in / 86400
            
            print("\n" + "=" * 80)
            print("✅ 長期トークンの取得に成功しました！")
            print("=" * 80)
            
            print(f"\n📅 有効期限: {expiry_date.strftime('%Y-%m-%d %H:%M:%S')} ({days:.0f}日後)")
            
            print("\n🔑 長期トークン:")
            print("-" * 80)
            print(long_token)
            print("-" * 80)
            
            # instagram_config.py への更新指示
            print("\n📝 次のステップ:")
            print("1. 上記のトークンをコピー")
            print("2. instagram_config.py を開く")
            print("3. page_access_token の値を更新")
            print("\n例:")
            print("INSTAGRAM_CONFIG = {")
            print(f"    'page_access_token': '{long_token[:50]}...',")
            print("    ...")
            print("}")
            
            # ファイルに保存
            save_token_to_file(long_token, expiry_date)
            
            return long_token
            
        else:
            error = result.get("error", {})
            error_message = error.get("message", "不明なエラー")
            print(f"\n❌ エラー: {error_message}")
            print("\n考えられる原因:")
            print("  - アプリシークレットが間違っている")
            print("  - 短期トークンが期限切れ")
            print("  - アプリIDが間違っている")
            return None
    
    except Exception as e:
        print(f"\n❌ エラー: {e}")
        return None

def save_token_to_file(token, expiry_date):
    """
    トークンをファイルに保存
    """
    data = {
        "access_token": token,
        "expiry_date": expiry_date.strftime('%Y-%m-%d %H:%M:%S'),
        "created_at": datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    }
    
    filename = "long_lived_token.json"
    
    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    
    print(f"\n💾 トークンを {filename} に保存しました")

def main():
    print("\n" + "=" * 80)
    print("Instagram 長期トークン取得ツール")
    print("=" * 80)
    
    # 入力確認
    if APP_SECRET == "YOUR_APP_SECRET":
        print("\n❌ エラー: APP_SECRET を設定してください")
        print("\n手順:")
        print("1. https://developers.facebook.com/apps/ を開く")
        print("2. あなたのアプリを選択")
        print("3. 左メニューから「設定」→「ベーシック」")
        print("4. 「アプリシークレット」の「表示」をクリック")
        print("5. パスワードを入力して表示")
        print("6. このスクリプトの APP_SECRET に貼り付け")
        return
    
    if SHORT_LIVED_TOKEN == "YOUR_SHORT_LIVED_TOKEN":
        print("\n❌ エラー: SHORT_LIVED_TOKEN を設定してください")
        print("\n手順:")
        print("1. https://developers.facebook.com/tools/explorer/ を開く")
        print("2. 現在のトークンをコピー")
        print("3. このスクリプトの SHORT_LIVED_TOKEN に貼り付け")
        return
    
    # 長期トークン取得
    long_token = get_long_lived_token(APP_ID, APP_SECRET, SHORT_LIVED_TOKEN)
    
    if long_token:
        print("\n" + "=" * 80)
        print("🎉 完了しました！")
        print("=" * 80)
        print("\ninstagram_config.py を更新してから、以下でテストしてください:")
        print("  python check_instagram_config.py")

if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n❌ 中断されました")
    except Exception as e:
        print(f"\n❌ 予期しないエラー: {e}")
        import traceback
        traceback.print_exc()