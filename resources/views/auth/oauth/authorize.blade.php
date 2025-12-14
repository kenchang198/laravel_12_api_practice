<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アプリケーションの認証</title>
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
            max-width: 400px;
            width: 100%;
        }
        h1 {
            margin-top: 0;
            color: #333;
        }
        .client-name {
            font-weight: bold;
            color: #2563eb;
            margin: 1rem 0;
        }
        .scopes {
            background-color: #f9fafb;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .scopes ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
        .buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        button {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .authorize-btn {
            background-color: #2563eb;
            color: white;
        }
        .authorize-btn:hover {
            background-color: #1d4ed8;
        }
        .cancel-btn {
            background-color: #e5e7eb;
            color: #374151;
        }
        .cancel-btn:hover {
            background-color: #d1d5db;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>アプリケーションの認証</h1>
        
        <p>以下のアプリケーションがあなたのアカウントへのアクセスを要求しています：</p>
        
        <div class="client-name">{{ $client->name }}</div>
        
        @if (count($scopes) > 0)
        <div class="scopes">
            <p><strong>このアプリケーションは以下の権限を要求しています：</strong></p>
            <ul>
                @foreach ($scopes as $scope)
                <li>{{ $scope->description ?? $scope->id }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        
        <form method="post" action="{{ route('passport.authorizations.approve') }}" id="approve-form">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            
            <div class="buttons">
                <button type="submit" class="authorize-btn">許可</button>
                <button type="button" class="cancel-btn" id="deny-btn">拒否</button>
            </div>
        </form>

        <form method="post" action="{{ route('passport.authorizations.deny') }}" id="deny-form" style="display: none;">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
        </form>

        <script>
            document.getElementById('deny-btn').addEventListener('click', function() {
                document.getElementById('deny-form').submit();
            });
        </script>
    </div>
</body>
</html>

