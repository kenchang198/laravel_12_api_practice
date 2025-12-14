# OAuth2.0 認証ハンズオンガイド

## 目次

1. [OAuth2.0 の基本概念](#oauth20の基本概念)
2. [Laravel Passport とは](#laravel-passportとは)
3. [セットアップ手順](#セットアップ手順)
4. [OAuth2.0 フロー図](#oauth20フロー図)
5. [実践：トークン取得から API 呼び出しまで](#実践トークン取得からapi呼び出しまで)
6. [トラブルシューティング](#トラブルシューティング)

---

## OAuth2.0 の基本概念

### OAuth2.0 とは

OAuth2.0 は、サードパーティアプリケーションがユーザーのリソースにアクセスするための認可フレームワークです。ユーザーは、パスワードを直接共有することなく、アプリケーションに特定の権限を付与できます。

### 主要な用語

| 用語                     | 説明                                                     |
| ------------------------ | -------------------------------------------------------- |
| **リソースオーナー**     | リソースを所有するユーザー（例：あなた）                 |
| **クライアント**         | リソースにアクセスしたいアプリケーション                 |
| **認証サーバー**         | アクセストークンを発行するサーバー（この Laravel API）   |
| **リソースサーバー**     | 保護されたリソースを提供するサーバー（この Laravel API） |
| **認証コード**           | アクセストークンと交換する一時的なコード                 |
| **アクセストークン**     | API にアクセスするためのトークン                         |
| **リフレッシュトークン** | 新しいアクセストークンを取得するためのトークン           |

### Authorization Code Grant フロー

このハンズオンで実装するフローです。最も安全で一般的な OAuth2.0 フローです。

**特徴：**

-   クライアントシークレットを使用するため、機密クライアントに適している
-   認証コードは短時間で有効期限が切れる
-   ブラウザを経由してユーザー認証を行う

---

## Laravel Passport とは

Laravel Passport は、Laravel アプリケーションに OAuth2.0 サーバー機能を提供するパッケージです。

**主な機能：**

-   OAuth2.0 標準に準拠した認証サーバー
-   複数の認証フロー（Authorization Code、Client Credentials、Password Grant 等）をサポート
-   アクセストークンとリフレッシュトークンの自動管理
-   トークンの有効期限管理

---

## 前提条件

このハンズオンでは以下の環境を使用します：

-   **Laravel**: 12.x
-   **Laravel Passport**: 15.x
-   **データベース**: SQLite（`database/database.sqlite`）
-   **PHP**: 8.2 以上

**注意：** Laravel 11 以降（Passport 15）では、以下の変更があります：

-   `Passport::routes()`メソッドが削除され、ルートは自動的に登録されます
-   OAuth2.0 クライアントの ID は UUID 形式になります
-   `oauth_clients`テーブルの構造が変更されました（`redirect_uris`、`grant_types`カラムを使用）

---

## セットアップ手順

### 1. パッケージのインストール

```bash
composer require laravel/passport
```

または、既に`composer.json`に追加されている場合：

```bash
composer install
```

### 2. データベースの設定

このプロジェクトは SQLite データベースを使用します。`.env`ファイルで以下の設定を確認してください：

```env
DB_CONNECTION=sqlite
DB_DATABASE=/Users/ken/dev/laravel_12_api/database/database.sqlite
```

**注意：** `DB_DATABASE`には、プロジェクトの絶対パスを指定してください。

SQLite データベースファイルが存在しない場合は、自動的に作成されます。

### 3. Passport のマイグレーションファイルの公開

Passport のマイグレーションファイルを公開します：

```bash
php artisan vendor:publish --tag=passport-migrations
```

このコマンドで、`database/migrations`ディレクトリに Passport のマイグレーションファイルがコピーされます。

### 4. Passport のマイグレーション実行

```bash
php artisan migrate
```

このコマンドで以下のテーブルが作成されます：

-   `oauth_clients` - OAuth2.0 クライアント情報（Passport 15 では`redirect_uris`と`grant_types`カラムを使用）
-   `oauth_access_tokens` - アクセストークン
-   `oauth_refresh_tokens` - リフレッシュトークン
-   `oauth_auth_codes` - 認証コード
-   `oauth_device_codes` - デバイスコード（Passport 15 で追加）

### 5. Passport の暗号化キー生成

```bash
php artisan passport:keys
```

このコマンドで以下のファイルが生成されます：

-   `storage/oauth-private.key` - 秘密鍵（トークンの署名に使用）
-   `storage/oauth-public.key` - 公開鍵（トークンの検証に使用）

**重要：** これらのキーは機密情報です。Git にコミットしないでください。`.gitignore`に`/storage/*.key`が含まれていることを確認してください。

### 6. 認証画面ビューの設定

Laravel Passport 15 では、認証画面のビューを設定する必要があります。

**6.1 AppServiceProvider の設定**

`app/Providers/AppServiceProvider.php`の`boot()`メソッドに以下を追加：

```php
public function boot(): void
{
    // Passportの認証画面ビューを設定
    Passport::authorizationView('auth.oauth.authorize');

    // Passportのトークン有効期限設定
    Passport::tokensExpireIn(now()->addHours(1));
    Passport::refreshTokensExpireIn(now()->addDays(30));
}
```

**6.2 認証画面ビューファイルの作成**

`resources/views/auth/oauth/authorize.blade.php`ファイルが存在することを確認してください。このファイルは、OAuth2.0 認証時にユーザーに表示される「許可/拒否」画面です。

**注意：** このファイルは既にプロジェクトに含まれています。存在しない場合は、`resources/views/auth/oauth/`ディレクトリを作成してからビューファイルを作成してください。

**6.3 ログイン画面の作成**

OAuth2.0 認証フローでは、ユーザーがブラウザでログインする必要があります。以下のファイルが既にプロジェクトに含まれています：

-   `routes/web.php` - ログインルート（`/login`）が定義されています
-   `resources/views/auth/login.blade.php` - ログイン画面のビュー
-   `app/Http/Controllers/AuthController.php` - ログイン処理が実装されています

**注意：** これらのファイルは既にプロジェクトに含まれています。存在しない場合は作成してください。

### 7. OAuth2.0 クライアントの作成

テスト用の OAuth2.0 クライアントを作成します：

```bash
php artisan db:seed --class=OAuthClientSeeder
```

実行後、以下の情報が表示されます：

-   **Client ID** - クライアント識別子（UUID 形式）
-   **Client Secret** - クライアントシークレット（平文で表示）

**ハンズオン用の Client Secret：**

このハンズオンでは、固定の Client Secret（`handson-test-secret-12345678901234567890`）を使用しています。シーダーを何度実行しても同じ値が使用されるため、アクセストークン取得時にこの固定値を使用してください。

**注意：** 本番環境では、必ずランダムな Client Secret を使用し、環境変数で管理してください。

**Passport 15 の変更点：**

-   クライアント ID は UUID 形式になります
-   `redirect_uris`は JSON 配列形式で保存されます
-   `grant_types`は JSON 配列形式で保存されます

### 8. テストユーザーの作成（オプション）

データベースにテストユーザーが存在しない場合：

```bash
php artisan tinker
```

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => Hash::make('password123'),
]);
```

**注意：** テストユーザーを作成したら、`exit`で tinker を終了してください。

---

## OAuth2.0 フロー図

```mermaid
sequenceDiagram
    participant User as ユーザー（ブラウザ）
    participant Client as クライアントアプリ
    participant AuthServer as Laravel API<br/>(認証サーバー)
    participant ResourceAPI as Laravel API<br/>(リソースサーバー)

    Note over Client,AuthServer: ステップ1: 認証コード取得
    Client->>User: 1. 認証URLにリダイレクト
    User->>AuthServer: 2. GET /oauth/authorize?<br/>client_id=xxx&<br/>redirect_uri=yyy&<br/>response_type=code&<br/>scope=&state=zzz
    AuthServer->>User: 3. ログインページ表示
    User->>AuthServer: 4. 認証情報送信（email/password）
    AuthServer->>User: 5. 認証コード付きリダイレクト<br/>Location: callback?code=abc123&state=zzz
    User->>Client: 6. 認証コードを返す

    Note over Client,AuthServer: ステップ2: アクセストークン取得
    Client->>AuthServer: 7. POST /oauth/token<br/>grant_type=authorization_code&<br/>client_id=xxx&<br/>client_secret=secret&<br/>code=abc123&<br/>redirect_uri=yyy
    AuthServer->>Client: 8. アクセストークン + リフレッシュトークン<br/>{access_token, refresh_token, expires_in}

    Note over Client,ResourceAPI: ステップ3: 保護されたAPI呼び出し
    Client->>ResourceAPI: 9. GET /api/user<br/>Authorization: Bearer {access_token}
    ResourceAPI->>Client: 10. ユーザー情報を返す<br/>{id, name, email, ...}
```

---

## 実践：トークン取得から API 呼び出しまで

### 前提条件

-   Laravel API サーバーが起動している（`php artisan serve`）
-   OAuth2.0 クライアントが作成済み（Client ID と Client Secret を取得済み）
-   テストユーザーが存在する

### ステップ 1: 認証コードの取得

ブラウザで以下の URL にアクセスします：

**注意：** `{CLIENT_ID}`は、`oauth_clients`テーブルの`id`カラムの値です。

```
http://localhost:8000/oauth/authorize?client_id={CLIENT_ID}&redirect_uri=http://localhost:8000/callback&response_type=code&scope=&state=random_state_string
```

**パラメータ説明：**

-   `client_id` - OAuth2.0 クライアントの ID（`oauth_clients`テーブルの`id`カラムの値）
-   `redirect_uri` - 認証後のリダイレクト先 URL（クライアント登録時のものと完全に一致させる必要があります）
-   **重要**: `localhost`と`127.0.0.1`は異なるものとして扱われます
-   ブラウザの URL が`http://127.0.0.1:8000`の場合は、`redirect_uri`も`http://127.0.0.1:8000/callback`にする必要があります
-   または、クライアント登録時に両方の URI を登録しておくこともできます
-   `response_type` - `code`（認証コードを要求）
-   `scope` - 要求する権限スコープ（空でも可）
-   `state` - CSRF 対策のためのランダム文字列（ハンズオンでは任意の文字列で可、例: `abc123`。実際のシステムでは推測困難なランダム文字列を推奨）

**実行例：**

```
http://localhost:8000/oauth/authorize?client_id=ca67a067-90e3-4000-b545-2b87167d2629&redirect_uri=http://localhost:8000/callback&response_type=code&scope=&state=abc123
```

**注意：** `client_id`には、`oauth_clients`テーブルの`id`カラムの値（UUID 形式）を使用してください。

**ログイン画面について：**

未認証の状態で`/oauth/authorize`にアクセスすると、自動的にログイン画面（`/login`）にリダイレクトされます。ログイン画面で以下の認証情報を入力してください：

-   **メールアドレス**: `test@example.com`
-   **パスワード**: `password123`

ログイン成功後、自動的に元の OAuth 認証画面にリダイレクトされ、アプリケーションの認可画面が表示されます。

認証成功後、リダイレクト URL に認証コードが付与されます：

```
http://localhost:8000/callback?code={AUTHORIZATION_CODE}&state=abc123
```

**重要：** この認証コードをコピーしてください。次のステップで使用します。

**コールバックページについて：**

`/callback`エンドポイントにリダイレクトされると、認証コードが表示されたページが表示されます。このページから認証コードをコピーして、次のステップ（アクセストークンの取得）で使用してください。

### ステップ 2: アクセストークンの取得

curl コマンドまたは Postman を使用して、認証コードをアクセストークンと交換します：

```bash
curl -X POST http://localhost:8000/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "Accept: application/json" \
  -d "grant_type=authorization_code" \
  -d "client_id={CLIENT_ID}" \
  -d "client_secret=handson-test-secret-12345678901234567890" \
  -d "redirect_uri=http://127.0.0.1:8000/callback" \
  -d "code={AUTHORIZATION_CODE}"
```

**実行例：**

```bash
curl -X POST http://localhost:8000/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "Accept: application/json" \
  -d "grant_type=authorization_code" \
  -d "client_id=94e67019-3ab2-4663-b20e-4dc6448dafbc" \
  -d "client_secret=handson-test-secret-12345678901234567890" \
  -d "redirect_uri=http://127.0.0.1:8000/callback" \
  -d "code={認証コード}"
```

**重要：**

-   `client_id`には、シーダー実行時に表示された実際の Client ID を使用してください
-   `client_secret`には、固定値`handson-test-secret-12345678901234567890`を使用してください（ハンズオン用）
-   `Content-Type`は`application/x-www-form-urlencoded`を使用してください（JSON ではありません）
-   `redirect_uri`は、認証コード取得時に使用したものと完全に一致させる必要があります（`localhost`と`127.0.0.1`は異なります）

**成功時のレスポンス：**

```json
{
    "token_type": "Bearer",
    "expires_in": 3600,
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "def50200a1b2c3d4e5f6..."
}
```

**重要：** `access_token`をコピーしてください。次のステップで使用します。

### ステップ 3: 保護された API の呼び出し

取得したアクセストークンを使用して、保護された API エンドポイントにアクセスします：

```bash
curl -X GET http://localhost:8000/api/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {ACCESS_TOKEN}"
```

**実行例：**

```bash
curl -X GET http://localhost:8000/api/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

**成功時のレスポンス：**

```json
{
    "id": 1,
    "name": "Test User",
    "email": "test@example.com",
    "email_verified_at": null,
    "created_at": "2025-01-01T00:00:00.000000Z"
}
```

### ステップ 4: リフレッシュトークンでアクセストークンを更新

アクセストークンが有効期限切れになった場合、リフレッシュトークンを使用して新しいアクセストークンを取得できます：

```bash
curl -X POST http://localhost:8000/oauth/token \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "grant_type": "refresh_token",
    "refresh_token": "{REFRESH_TOKEN}",
    "client_id": "97d7660d-0b6e-4a2d-b597-3a0027e387be",
    "client_secret": "1r0iQbH3A46nBDgPGlu1UASYCiXa7S0vm8rIX54L",
    "scope": ""
  }'
```

**注意：** `client_id`と`client_secret`には、シーダーで作成された実際の値を使用してください。

---

## Postman でのテスト手順

### 1. 認証コード取得リクエスト

**メソッド：** GET  
**URL：** `http://localhost:8000/oauth/authorize`  
**パラメータ（Query Params）：**

-   `client_id`: `97d7660d-0b6e-4a2d-b597-3a0027e387be`（あなたの Client ID - UUID 形式）
-   `redirect_uri`: `http://localhost:8000/callback`
-   `response_type`: `code`
-   `scope`: （空）
-   `state`: `test123`

**注意：** このリクエストはブラウザで実行してください。Postman では認証画面が表示されない場合があります。

### 2. アクセストークン取得リクエスト

**メソッド：** POST  
**URL：** `http://localhost:8000/oauth/token`  
**Headers：**

-   `Content-Type`: `application/json`
-   `Accept`: `application/json`

**Body (raw JSON)：**

```json
{
    "grant_type": "authorization_code",
    "client_id": "97d7660d-0b6e-4a2d-b597-3a0027e387be",
    "client_secret": "1r0iQbH3A46nBDgPGlu1UASYCiXa7S0vm8rIX54L",
    "redirect_uri": "http://localhost:8000/callback",
    "code": "認証コードをここに入力"
}
```

**注意：** `client_id`と`client_secret`には、シーダーで作成された実際の値を使用してください。

### 3. 保護された API 呼び出し

**メソッド：** GET  
**URL：** `http://localhost:8000/api/user`  
**Headers：**

-   `Accept`: `application/json`
-   `Authorization`: `Bearer {アクセストークン}`

---

## 簡易ログインエンドポイント

OAuth2.0 フローを理解する前に、簡単にユーザー認証をテストしたい場合は、以下のエンドポイントを使用できます：

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'
```

このエンドポイントは認証情報を検証するだけで、OAuth2.0 トークンは発行しません。OAuth2.0 フローを理解するための補助として使用してください。

---

## トラブルシューティング

### エラー: "Client authentication failed"

**原因：** Client ID または Client Secret が間違っている

**解決方法：**

-   Client ID と Client Secret を再確認
-   データベースの`oauth_clients`テーブルを確認：

    ```bash
    php artisan tinker
    ```

    ```php
    use Laravel\Passport\Client;

    // すべてのクライアントを表示
    Client::all();

    // 特定のクライアントを表示
    $client = Client::where('name', 'Hands-on Test Client')->first();
    echo 'Client ID: ' . $client->id . PHP_EOL;
    echo 'Client Secret: ' . $client->secret . PHP_EOL;
    ```

**注意：** Passport 15 では、Client Secret は平文で保存されますが、データベースから直接確認する場合は注意してください。

### エラー: "The authorization code has expired"

**原因：** 認証コードの有効期限（通常 10 分）が切れた

**解決方法：**

-   認証コードを再取得してください

### エラー: "Invalid redirect URI" または "Client authentication failed"

**原因：** リクエストの`redirect_uri`がクライアント登録時のものと一致しない

**解決方法：**

-   クライアント登録時の`redirect_uri`を確認
-   **重要**: `localhost`と`127.0.0.1`は異なるものとして扱われます
-   ブラウザの URL が`http://127.0.0.1:8000`の場合は、`redirect_uri`も`http://127.0.0.1:8000/callback`にする必要があります
-   または、クライアント登録時に両方の URI を登録しておくこともできます（シーダーで既に対応済み）
-   データベースの`oauth_clients`テーブルの`redirect_uris`カラムを確認：

    ```bash
    php artisan tinker
    ```

    ```php
    use Laravel\Passport\Client;

    $client = Client::where('name', 'Hands-on Test Client')->first();
    echo json_encode($client->redirect_uris);
    ```

-   リクエストの`redirect_uri`と完全に一致させる（スラッシュの有無も含む）

### エラー: "Route [login] not defined"

**原因：** OAuth2.0 認証フローでログインが必要な場合、`login`ルートが定義されていない

**解決方法：**

-   `routes/web.php`にログインルートが定義されているか確認
-   ログインビュー（`resources/views/auth/login.blade.php`）が存在するか確認
-   以下のコマンドでルートを確認：

    ```bash
    php artisan route:list | grep login
    ```

**注意：** OAuth2.0 の Authorization Code Grant フローでは、ブラウザでのログインが必要なため、セッション認証用のログインページが必要です。

### エラー: "Unauthenticated" (401)

**原因：** アクセストークンが無効、または有効期限切れ

**解決方法：**

-   アクセストークンが正しく設定されているか確認
-   リフレッシュトークンで新しいアクセストークンを取得

### エラー: "Call to undefined method Laravel\Passport\Passport::routes()"

**原因：** Laravel 11 以降（Passport 15）では`Passport::routes()`メソッドが削除されました

**解決方法：**

-   `Passport::routes()`の呼び出しを削除してください
-   Laravel 11 以降では、Passport のルートは自動的に登録されます
-   `app/Providers/AppServiceProvider.php`から`Passport::routes();`の行を削除してください

### エラー: "no such table: oauth_clients"

**原因：** Passport のマイグレーションファイルが公開されていない、またはマイグレーションが実行されていない

**解決方法：**

1. Passport のマイグレーションファイルを公開：
    ```bash
    php artisan vendor:publish --tag=passport-migrations
    ```
2. マイグレーションを実行：
    ```bash
    php artisan migrate
    ```

### エラー: "unable to open database file" (SQLite)

**原因：** SQLite データベースファイルのパスが正しく設定されていない、または権限の問題

**解決方法：**

-   `.env`ファイルで`DB_DATABASE`に絶対パスを設定：
    ```env
    DB_DATABASE=/Users/ken/dev/laravel_12_api/database/database.sqlite
    ```
-   データベースファイルの権限を確認：
    ```bash
    chmod 664 database/database.sqlite
    ```

### マイグレーションエラー

**原因：** データベース接続の問題、または既存のテーブルとの競合

**解決方法：**

-   データベース接続を確認（`.env`ファイル）
-   SQLite を使用している場合、`DB_DATABASE`に絶対パスが設定されているか確認
-   既存のテーブルを確認：
    ```bash
    php artisan migrate:status
    ```

---

## セキュリティのベストプラクティス

1. **HTTPS の使用**

    - 本番環境では必ず HTTPS を使用してください
    - Client Secret やアクセストークンは平文で送信されるため、HTTPS が必須です

2. **Client Secret の保護**

    - Client Secret は環境変数で管理してください
    - Git にコミットしないでください

3. **トークンの有効期限**

    - アクセストークンは短い有効期限（1 時間程度）を設定
    - リフレッシュトークンで定期的に更新

4. **State パラメータの使用**

    - CSRF 攻撃を防ぐため、`state`パラメータを必ず使用してください
    - リダイレクト時に`state`が一致するか確認

5. **スコープの適切な設定**
    - 必要最小限の権限のみを要求してください
    - スコープごとに異なる権限を設定できます

---

## 参考リソース

-   [Laravel Passport 公式ドキュメント](https://laravel.com/docs/passport)
-   [OAuth 2.0 仕様書（RFC 6749）](https://tools.ietf.org/html/rfc6749)
-   [OWASP OAuth2.0 Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/OAuth2_Cheat_Sheet.html)

---

## 次のステップ

1. **Password Grant フローの実装**

    - モバイルアプリ向けの簡易認証フロー

2. **スコープの実装**

    - 権限ごとに異なるスコープを設定

3. **トークンの無効化機能**

    - ユーザーが特定のデバイスからログアウトできる機能

4. **クライアント管理 UI**
    - OAuth2.0 クライアントを管理する管理画面の作成
