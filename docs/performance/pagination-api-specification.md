# ページネーションAPI仕様書

## 概要

このAPI仕様書では、ページネーション機能の動作確認用に実装された2つのエンドポイントについて説明します。

- **ベースURL**: `http://localhost/api`
- **データ形式**: JSON
- **認証**: 不要（モックデータを使用）

---

## 1. オフセット&リミット方式

### エンドポイント

```
GET /api/users/offset
```

### 説明

従来型のページネーション方式。`offset`（開始位置）と`limit`（取得件数）を指定してデータを取得します。

### リクエストパラメータ

| パラメータ | 型 | 必須 | デフォルト | 説明 | 制約 |
|-----------|-----|------|-----------|------|------|
| `limit` | integer | 任意 | 10 | 取得件数 | 1以上100以下 |
| `offset` | integer | 任意 | 0 | 開始位置（スキップする件数） | 0以上 |

### リクエスト例

```bash
# デフォルト（最初の10件）
curl -X GET "http://localhost/api/users/offset"

# 21件目から30件目を取得
curl -X GET "http://localhost/api/users/offset?limit=10&offset=20"

# カスタム件数
curl -X GET "http://localhost/api/users/offset?limit=25&offset=0"
```

### レスポンス形式

#### 成功時（200 OK）

```json
{
  "data": [
    {
      "id": 1,
      "name": "山田 太郎",
      "email": "yamada@example.com",
      "created_at": "2024-12-01 10:30:00"
    },
    {
      "id": 2,
      "name": "佐藤 花子",
      "email": "sato@example.com",
      "created_at": "2024-12-02 14:20:00"
    }
  ],
  "meta": {
    "total": 100,
    "limit": 10,
    "offset": 20,
    "has_more": true
  }
}
```

#### レスポンスフィールド

**data** (array)
- ユーザーデータの配列
- 各ユーザーオブジェクトには以下のフィールドが含まれます：
  - `id` (integer): ユーザーID
  - `name` (string): ユーザー名
  - `email` (string): メールアドレス
  - `created_at` (string): 作成日時（Y-m-d H:i:s形式）

**meta** (object)
- `total` (integer): 総件数
- `limit` (integer): リクエストで指定された取得件数
- `offset` (integer): リクエストで指定された開始位置
- `has_more` (boolean): 次のページが存在するかどうか

### エラーレスポンス

#### バリデーションエラー（422 Unprocessable Entity）

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "limit": [
      "The limit must be at least 1."
    ],
    "offset": [
      "The offset must be at least 0."
    ]
  }
}
```

---

## 2. カーソルベース方式

### エンドポイント

```
GET /api/users/cursor
```

### 説明

カーソルベースのページネーション方式。最後に取得したレコードの識別子（カーソル）を基準に、次のデータセットを取得します。大規模データに適しています。

### リクエストパラメータ

| パラメータ | 型 | 必須 | デフォルト | 説明 | 制約 |
|-----------|-----|------|-----------|------|------|
| `per_page` | integer | 任意 | 15 | 1ページあたりの取得件数 | 1以上100以下 |
| `cursor` | string | 任意 | null | カーソル位置（Base64エンコードされたJSON） | - |

### カーソルの仕組み

カーソルはBase64エンコードされたJSON文字列です。デコードすると以下の形式になります：

```json
{"id": 15}
```

- `id`: 最後に取得したレコードのID
- 次ページ取得時は、このIDより大きいレコードから取得されます

### リクエスト例

```bash
# 初回リクエスト（最初の15件）
curl -X GET "http://localhost/api/users/cursor"

# 次ページ取得（前回のレスポンスのnext_cursorを使用）
curl -X GET "http://localhost/api/users/cursor?per_page=15&cursor=eyJpZCI6MTV9"

# カスタム件数
curl -X GET "http://localhost/api/users/cursor?per_page=20"
```

### レスポンス形式

#### 成功時（200 OK）

```json
{
  "data": [
    {
      "id": 1,
      "name": "山田 太郎",
      "email": "yamada@example.com",
      "created_at": "2024-12-01 10:30:00"
    },
    {
      "id": 2,
      "name": "佐藤 花子",
      "email": "sato@example.com",
      "created_at": "2024-12-02 14:20:00"
    }
  ],
  "meta": {
    "per_page": 15,
    "next_cursor": "eyJpZCI6MTV9",
    "prev_cursor": null,
    "has_next": true,
    "has_prev": false
  }
}
```

#### レスポンスフィールド

**data** (array)
- ユーザーデータの配列（オフセット方式と同じ形式）

**meta** (object)
- `per_page` (integer): リクエストで指定された1ページあたりの件数
- `next_cursor` (string|null): 次ページ取得用のカーソル（存在しない場合はnull）
- `prev_cursor` (string|null): 前ページ取得用のカーソル（存在しない場合はnull）
- `has_next` (boolean): 次のページが存在するかどうか
- `has_prev` (boolean): 前のページが存在するかどうか

### カーソルの使用例

#### 1. 初回リクエスト

```bash
curl -X GET "http://localhost/api/users/cursor?per_page=15"
```

レスポンス例：
```json
{
  "data": [...],
  "meta": {
    "per_page": 15,
    "next_cursor": "eyJpZCI6MTV9",
    "prev_cursor": null,
    "has_next": true,
    "has_prev": false
  }
}
```

#### 2. 次ページ取得

前回のレスポンスの`next_cursor`を使用：

```bash
curl -X GET "http://localhost/api/users/cursor?per_page=15&cursor=eyJpZCI6MTV9"
```

レスポンス例：
```json
{
  "data": [...],
  "meta": {
    "per_page": 15,
    "next_cursor": "eyJpZCI6MzB9",
    "prev_cursor": "eyJpZCI6MTV9",
    "has_next": true,
    "has_prev": true
  }
}
```

#### 3. 前ページ取得

前回のレスポンスの`prev_cursor`を使用：

```bash
curl -X GET "http://localhost/api/users/cursor?per_page=15&cursor=eyJpZCI6MTV9"
```

### エラーレスポンス

#### バリデーションエラー（422 Unprocessable Entity）

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "per_page": [
      "The per page must be at least 1."
    ]
  }
}
```

#### 無効なカーソル（400 Bad Request）

無効なカーソルが指定された場合、最初のページが返されます。

---

## 動作確認手順

### 1. オフセット&リミット方式の確認

```bash
# 1. 最初の10件を取得
curl -X GET "http://localhost/api/users/offset"

# 2. 21件目から30件目を取得
curl -X GET "http://localhost/api/users/offset?limit=10&offset=20"

# 3. 総件数を確認（meta.totalを確認）
curl -X GET "http://localhost/api/users/offset?limit=1&offset=0"
```

### 2. カーソルベース方式の確認

```bash
# 1. 初回リクエスト
curl -X GET "http://localhost/api/users/cursor?per_page=15"

# 2. レスポンスのnext_cursorをコピーして次ページ取得
curl -X GET "http://localhost/api/users/cursor?per_page=15&cursor=eyJpZCI6MTV9"

# 3. 前ページに戻る（prev_cursorを使用）
curl -X GET "http://localhost/api/users/cursor?per_page=15&cursor=eyJpZCI6MTV9"
```

### 3. エラーハンドリングの確認

```bash
# limitが上限を超える場合
curl -X GET "http://localhost/api/users/offset?limit=101"

# offsetが負の値の場合
curl -X GET "http://localhost/api/users/offset?offset=-1"

# per_pageが上限を超える場合
curl -X GET "http://localhost/api/users/cursor?per_page=101"
```

---

## 注意事項

1. **モックデータ**: このAPIはデータベースを使用せず、100件のモックデータを生成します。リクエストごとに同じデータが返されます。

2. **カーソルの有効期限**: カーソルに有効期限はありませんが、データが変更された場合、カーソルが無効になる可能性があります。

3. **パフォーマンス**: オフセット方式は`offset`値が大きくなると性能が劣化します。大規模データにはカーソルベース方式を推奨します。

4. **ソート順**: データはIDの昇順でソートされています。

---

## 関連ドキュメント

- [ページネーション実装ガイド](pagination.md)

