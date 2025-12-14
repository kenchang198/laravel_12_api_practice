<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth2.0 認証コード取得完了</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            width: 100%;
        }
        h1 {
            margin-top: 0;
            color: #333;
        }
        .success {
            background-color: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .error {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .code-block {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            max-height: 200px;
            overflow-y: auto;
        }
        .info {
            background-color: #eff6ff;
            border: 1px solid #3b82f6;
            color: #1e40af;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .button {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 1rem;
            transition: background-color 0.2s;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        .copy-btn {
            background-color: #6b7280;
            margin-left: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        .copy-btn:hover {
            background-color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>OAuth2.0 認証コード取得</h1>
        
        @if ($error)
            <div class="error">
                <strong>エラーが発生しました：</strong><br>
                {{ $error_description ?? $error }}
            </div>
        @else
            <div class="success">
                <strong>✓ 認証コードが正常に取得できました！</strong>
            </div>

            <div class="info">
                <strong>次のステップ：</strong><br>
                以下の認証コードをコピーして、アクセストークン取得のリクエストで使用してください。
            </div>

            <div>
                <strong>認証コード (Authorization Code):</strong>
                <div class="code-block" id="code-block">{{ $code }}</div>
                <button class="button copy-btn" onclick="copyToClipboard('{{ $code }}')">コピー</button>
            </div>

            @if ($state)
            <div style="margin-top: 1rem;">
                <strong>State:</strong>
                <div class="code-block">{{ $state }}</div>
            </div>
            @endif

            <div class="info" style="margin-top: 1.5rem;">
                <strong>次のステップ：アクセストークンの取得</strong><br>
                以下のコマンドを実行して、アクセストークンを取得してください：<br><br>
                <div class="code-block" style="font-size: 0.75rem;">
curl -X POST http://localhost:8000/oauth/token \<br>
&nbsp;&nbsp;-H "Content-Type: application/x-www-form-urlencoded" \<br>
&nbsp;&nbsp;-H "Accept: application/json" \<br>
&nbsp;&nbsp;-d "grant_type=authorization_code" \<br>
&nbsp;&nbsp;-d "client_id={CLIENT_ID}" \<br>
&nbsp;&nbsp;-d "client_secret={CLIENT_SECRET}" \<br>
&nbsp;&nbsp;-d "redirect_uri=http://127.0.0.1:8000/callback" \<br>
&nbsp;&nbsp;-d "code={{ $code }}"
                </div>
            </div>
        @endif

        <a href="/" class="button">トップページに戻る</a>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('認証コードをクリップボードにコピーしました');
            }, function(err) {
                console.error('コピーに失敗しました:', err);
                // フォールバック: テキストエリアを使用
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('認証コードをクリップボードにコピーしました');
            });
        }
    </script>
</body>
</html>

