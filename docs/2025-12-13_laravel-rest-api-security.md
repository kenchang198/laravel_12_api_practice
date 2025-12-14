# Laravel 12 REST API セキュリティ設計マニュアル

## 1. 認証・認可設定

### 1.1 認証（Authentication）

**概要**
認証は「ユーザーが誰であるか」を確認するプロセス。APIでは主にトークンベースの認証を使用する。

**Laravel での認証方式**

| 方式 | 特徴 | 用途 |
|------|------|------|
| Laravel Sanctum | シンプル、SPA/モバイル向け | 自社アプリ向けAPI |
| Laravel Passport | OAuth2.0準拠、フル機能 | サードパーティ連携API |
| JWT（tymon/jwt-auth） | ステートレス、自己完結型 | マイクロサービス |

**Sanctum によるAPI認証の実装**

1. **トークン発行**: ログイン成功時に `createToken()` でトークンを生成
2. **トークン検証**: `auth:sanctum` ミドルウェアで保護
3. **トークン失効**: ログアウト時に `currentAccessToken()->delete()` で無効化

```php
// ルート保護
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
});
```

**トークン管理のベストプラクティス**

- トークンには有効期限を設定（`expiration` 設定）
- アビリティ（abilities）でトークンごとの権限を制限
- 機密操作時は追加認証（パスワード再確認等）を要求
- 不審なアクティビティ検知時は全トークンを失効

---

### 1.2 認可（Authorization）

**概要**
認可は「ユーザーが何を許可されているか」を確認するプロセス。認証済みユーザーでも、すべての操作が許可されるわけではない。

**Laravel での認可機能**

| 機能 | 用途 |
|------|------|
| Gate | シンプルな権限チェック（クロージャベース） |
| Policy | モデル単位の権限管理（推奨） |
| ミドルウェア | ルートレベルでのアクセス制御 |

**Policy による認可実装**

Policyはモデルに対する操作権限を定義するクラス。CRUD操作ごとにメソッドを定義する。

```php
// UserPolicy.php
public function update(User $authUser, User $targetUser): bool
{
    return $authUser->id === $targetUser->id 
        || $authUser->hasRole('admin');
}
```

**コントローラーでの認可チェック**

```php
public function update(Request $request, User $user)
{
    $this->authorize('update', $user); // 権限なければ403
    // 更新処理
}
```

**認可設計のポイント**

- **最小権限の原則**: 必要最低限の権限のみ付与
- **デフォルト拒否**: 明示的に許可されていない操作は拒否
- **オブジェクトレベル認可**: リソースの所有者確認を必ず実施（IDOR対策）
- **機能レベル認可**: 管理者専用エンドポイントの保護

---

## 2. 無制限のリソース消費対策

### 2.1 レートリミット

**概要**
一定時間内のAPIリクエスト数を制限し、DoS攻撃やAPIの乱用を防ぐ。

**Laravel標準のレートリミット**

`throttle` ミドルウェアで簡単に設定可能。

```php
// 1分間に60リクエストまで
Route::middleware('throttle:60,1')->group(function () {
    Route::apiResource('users', UserController::class);
});
```

**カスタムレートリミットの定義**

`AppServiceProvider` の `boot()` で `RateLimiter` ファサードを使用。

```php
// エンドポイントごとに異なる制限
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// 認証済みユーザーは緩和
RateLimiter::for('authenticated', function (Request $request) {
    return $request->user()
        ? Limit::perMinute(120)->by($request->user()->id)
        : Limit::perMinute(30)->by($request->ip());
});
```

**レートリミット時のレスポンス**

- ステータスコード: `429 Too Many Requests`
- ヘッダー: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`

---

### 2.2 その他のリソース制限

**ペイロードサイズの制限**

```php
// ミドルウェアでリクエストサイズを検証
if ($request->header('Content-Length') > 1048576) { // 1MB
    abort(413, 'Payload too large');
}
```

**クエリの複雑さ制限**

- ページネーションの `per_page` に上限を設定（例: max 100）
- ネストしたリレーションの深さを制限
- 一括操作の件数を制限

**タイムアウト設定**

長時間実行されるリクエストを防ぐため、適切なタイムアウトを設定。

```php
// php.ini または .htaccess
max_execution_time = 30
```

---

## 3. データ漏洩・過剰露出の防止

### 3.1 APIレスポンスの制御

**API Resource による出力制御**

モデルを直接返さず、必ずAPI Resourceを経由して必要なフィールドのみ返却。

```php
// ❌ 危険: 全フィールドが露出する可能性
return response()->json($user);

// ◯ 安全: 明示的に指定したフィールドのみ
return new UserResource($user);
```

**機密フィールドの除外**

```php
// UserResource.php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        // password, remember_token 等は含めない
    ];
}
```

**Model の $hidden 属性**

万が一モデルが直接シリアライズされた場合の保険として設定。

```php
// User.php
protected $hidden = ['password', 'remember_token', 'two_factor_secret'];
```

---

### 3.2 URLとクエリ文字列

**機密情報をURLに含めない**

```
❌ GET /api/users?api_key=xxx&password=yyy
❌ GET /api/documents/secret-token-123

◯ Authorization ヘッダーでトークンを送信
◯ POST ボディで機密データを送信
```

**理由**
- URLはブラウザ履歴、サーバーログ、リファラーヘッダーに記録される
- HTTPSでも暗号化されるのはボディ部分であり、URLはログに残りやすい

---

### 3.3 エラーメッセージの制御

**本番環境でのデバッグ情報非表示**

```env
# .env（本番）
APP_DEBUG=false
APP_ENV=production
```

**カスタム例外ハンドリング**

内部エラーの詳細をクライアントに露出させない。

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getClientSafeMessage($e),
            ], $this->getStatusCode($e));
        }
    });
})
```

**エラーメッセージの原則**

| 環境 | 表示内容 |
|------|---------|
| 開発 | 詳細なスタックトレース、SQLエラー等 |
| 本番 | 汎用的なメッセージ（「エラーが発生しました」等） |

**認証エラーの曖昧化**

ユーザー列挙攻撃を防ぐため、ログイン失敗時は具体的な理由を明かさない。

```php
// ❌ 「このメールアドレスは登録されていません」
// ❌ 「パスワードが間違っています」
// ◯ 「認証情報が正しくありません」
```

---

## 4. 入力データのバリデーション

### 4.1 基本的なバリデーション

**Form Request による検証**

すべての入力データは信頼せず、必ずサーバーサイドで検証する。

```php
// StoreUserRequest.php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users'],
        'age' => ['nullable', 'integer', 'min:0', 'max:150'],
    ];
}
```

**型の厳密な検証**

```php
'id' => ['required', 'integer'],           // 数値型
'status' => ['required', 'in:active,inactive'], // 許可値リスト
'date' => ['required', 'date_format:Y-m-d'],    // 日付形式
```

---

### 4.2 SQLインジェクション対策

**Eloquent / クエリビルダの使用**

Laravel のEloquentとクエリビルダは自動的にパラメータをエスケープする。

```php
// ◯ 安全: プレースホルダを使用
User::where('email', $request->email)->first();

// ❌ 危険: 生のSQL結合
DB::select("SELECT * FROM users WHERE email = '" . $request->email . "'");
```

**生SQLが必要な場合**

```php
// プレースホルダを必ず使用
DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

**カラム名の動的指定時の注意**

```php
// ❌ 危険
$sortColumn = $request->input('sort');
User::orderBy($sortColumn)->get();

// ◯ 安全: ホワイトリストで検証
$allowed = ['id', 'name', 'created_at'];
$sortColumn = in_array($request->input('sort'), $allowed) 
    ? $request->input('sort') 
    : 'id';
```

---

### 4.3 XSS（クロスサイトスクリプティング）対策

**出力時のエスケープ**

APIレスポンス（JSON）は基本的にXSSの影響を受けにくいが、HTMLとして解釈される可能性がある場合は注意。

```php
// HTMLエンティティにエスケープ
'comment' => e($this->comment),
// または
'comment' => htmlspecialchars($this->comment, ENT_QUOTES, 'UTF-8'),
```

**Content-Type の明示**

レスポンスが HTML として解釈されないよう、Content-Type を明示。

```php
return response()->json($data)
    ->header('Content-Type', 'application/json');
```

**入力値のサニタイズ**

HTMLタグを許可しない場合は `strip_tags()` で除去。

```php
'bio' => ['required', 'string', function ($attribute, $value, $fail) {
    if ($value !== strip_tags($value)) {
        $fail('HTMLタグは使用できません。');
    }
}],
```

---

### 4.4 マスアサインメント対策

**$fillable / $guarded の設定**

意図しないフィールドの一括更新を防ぐ。

```php
// User.php
protected $fillable = ['name', 'email']; // 許可するフィールド
// または
protected $guarded = ['id', 'is_admin']; // 禁止するフィールド
```

**リクエストからの取得時に明示**

```php
// ◯ 必要なフィールドのみ取得
$user->update($request->only(['name', 'email']));

// ❌ 危険: 全入力を受け入れ
$user->update($request->all());
```

---

## 5. HTTPヘッダーのセキュリティ設定

### 5.1 推奨セキュリティヘッダー

**ミドルウェアでの一括設定**

```php
// app/Http/Middleware/SecurityHeaders.php
public function handle(Request $request, Closure $next): Response
{
    $response = $next($request);

    return $response
        ->header('X-Content-Type-Options', 'nosniff')
        ->header('X-Frame-Options', 'DENY')
        ->header('X-XSS-Protection', '1; mode=block')
        ->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
        ->header('Content-Security-Policy', "default-src 'self'")
        ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->header('Permissions-Policy', 'geolocation=(), microphone=()');
}
```

---

### 5.2 各ヘッダーの説明

| ヘッダー | 説明 | 推奨値 |
|---------|------|--------|
| `X-Content-Type-Options` | MIMEタイプスニッフィング防止 | `nosniff` |
| `X-Frame-Options` | クリックジャッキング防止 | `DENY` または `SAMEORIGIN` |
| `Strict-Transport-Security` | HTTPS強制（HSTS） | `max-age=31536000; includeSubDomains` |
| `Content-Security-Policy` | XSS・データインジェクション防止 | アプリに応じて設定 |
| `Referrer-Policy` | リファラー情報の制御 | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | ブラウザ機能の制限 | 使用しない機能を無効化 |

---

### 5.3 CORS（Cross-Origin Resource Sharing）

**概要**
異なるオリジンからのAPIアクセスを制御する仕組み。

**設定ファイル**

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allowed_origins' => ['https://example.com'], // ワイルドカード '*' は避ける
    'allowed_headers' => ['Content-Type', 'Authorization'],
    'exposed_headers' => ['X-RateLimit-Remaining'],
    'max_age' => 86400,
    'supports_credentials' => true, // Cookieを使う場合
];
```

**CORS設定の原則**

- `allowed_origins` に `*` は使わない（認証付きAPIでは特に危険）
- 必要なメソッド・ヘッダーのみ許可
- `supports_credentials` は必要な場合のみ `true`

---

### 5.4 セキュリティヘッダーの検証

**オンラインツール**

- [Security Headers](https://securityheaders.com/) - ヘッダー設定のスコアリング
- [Mozilla Observatory](https://observatory.mozilla.org/) - 総合的なセキュリティ評価

**curl での確認**

```bash
curl -I https://api.example.com/users
```

---

## 6. 実装チェックリスト

### 認証・認可
- [ ] トークンベース認証を実装
- [ ] トークンに有効期限を設定
- [ ] 全APIエンドポイントに認可チェックを実装
- [ ] オブジェクトレベルの認可（所有者確認）を実施

### リソース制限
- [ ] レートリミットを設定
- [ ] ペイロードサイズの上限を設定
- [ ] ページネーションの上限を設定

### データ保護
- [ ] API Resourceで出力フィールドを明示的に制御
- [ ] Model の $hidden で機密フィールドを非表示
- [ ] 本番環境で APP_DEBUG=false を確認
- [ ] エラーメッセージから内部情報を除去

### 入力検証
- [ ] 全入力データをバリデーション
- [ ] Eloquent/クエリビルダでSQLインジェクション対策
- [ ] $fillable/$guarded でマスアサインメント対策
- [ ] 動的カラム名はホワイトリストで検証

### HTTPヘッダー
- [ ] セキュリティヘッダーをミドルウェアで設定
- [ ] CORS設定を適切に構成
- [ ] HTTPS を強制（HSTS）
- [ ] Security Headers等で設定を検証

---

## 7. 参考リソース

- [OWASP API Security Top 10](https://owasp.org/API-Security/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [CWE/SANS Top 25](https://cwe.mitre.org/top25/)
