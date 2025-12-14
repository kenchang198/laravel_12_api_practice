# MySQL Dockerコンテナセットアップガイド

## 目次

1. [概要](#概要)
2. [前提条件](#前提条件)
3. [セットアップ手順](#セットアップ手順)
4. [コンテナの操作](#コンテナの操作)
5. [データベースへの接続](#データベースへの接続)
6. [トラブルシューティング](#トラブルシューティング)

---

## 概要

このプロジェクトでは、Docker Composeを使用してMySQLコンテナを管理します。開発環境でMySQLデータベースを簡単に起動・停止できます。

**使用技術：**
- Docker
- Docker Compose
- MySQL 8.0

---

## 前提条件

- Dockerがインストールされていること
- Docker Composeが利用可能であること

**確認方法：**
```bash
docker --version
docker-compose --version
```

---

## セットアップ手順

### 1. MySQLコンテナの起動

```bash
docker-compose up -d mysql
```

このコマンドで以下が実行されます：
- MySQL 8.0のイメージをダウンロード（初回のみ）
- MySQLコンテナを起動
- データベース`laravel_db`を作成
- ポート3306でMySQLを公開

### 2. コンテナの状態確認

```bash
docker-compose ps
```

**正常な場合の出力例：**
```
NAME                      IMAGE       COMMAND                  SERVICE   CREATED         STATUS          PORTS
laravel_12_api_mysql      mysql:8.0   "docker-entrypoint.s…"   mysql     2 minutes ago   Up 2 minutes    0.0.0.0:3306->3306/tcp
```

### 3. データベース接続の確認

```bash
php artisan migrate --pretend
```

エラーが発生しなければ、接続は成功しています。

### 4. マイグレーションの実行

```bash
php artisan migrate
```

これで、SQLiteからMySQLへの移行が完了します。

---

## コンテナの操作

### コンテナの起動

```bash
docker-compose up -d mysql
```

### コンテナの停止

```bash
docker-compose stop mysql
```

### コンテナの再起動

```bash
docker-compose restart mysql
```

### コンテナの停止と削除

```bash
docker-compose down mysql
```

**注意：** `down`コマンドを実行すると、コンテナは削除されますが、データベースのデータはボリュームに保存されているため、次回起動時にもデータは残ります。

### コンテナとデータの完全削除

```bash
docker-compose down -v
```

**警告：** このコマンドを実行すると、データベースのデータも削除されます。

### コンテナのログ確認

```bash
# リアルタイムでログを表示
docker-compose logs -f mysql

# 最新の100行を表示
docker-compose logs --tail=100 mysql
```

---

## データベースへの接続

### Laravelから接続

`.env`ファイルで以下の設定がされていることを確認してください：

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=root
DB_PASSWORD=secret
```

### MySQLクライアントから接続

#### 方法1: Dockerコンテナ内のMySQLクライアントを使用

```bash
docker-compose exec mysql mysql -u root -psecret laravel_db
```

#### 方法2: ローカルのMySQLクライアントを使用

```bash
mysql -h 127.0.0.1 -P 3306 -u root -psecret laravel_db
```

**接続情報：**
- ホスト: `127.0.0.1`
- ポート: `3306`
- ユーザー名: `root`
- パスワード: `secret`
- データベース名: `laravel_db`

### よく使うMySQLコマンド

MySQLに接続したら、以下のコマンドが使えます：

```sql
-- データベース一覧を表示
SHOW DATABASES;

-- 現在のデータベースを選択
USE laravel_db;

-- テーブル一覧を表示
SHOW TABLES;

-- テーブル構造を確認
DESCRIBE oauth_clients;
DESCRIBE users;

-- データを確認
SELECT * FROM oauth_clients;
SELECT * FROM users;

-- 終了
EXIT;
```

---

## docker-compose.ymlの設定

プロジェクトルートの`docker-compose.yml`ファイルで、MySQLコンテナの設定を確認・変更できます。

### 主要な設定項目

```yaml
services:
  mysql:
    image: mysql:8.0                    # MySQLのバージョン
    ports:
      - "${DB_PORT:-3306}:3306"         # ポートマッピング
    environment:
      MYSQL_DATABASE: "${DB_DATABASE:-laravel_db}"      # データベース名
      MYSQL_ROOT_PASSWORD: "${DB_PASSWORD:-secret}"     # rootパスワード
      MYSQL_PASSWORD: "${DB_PASSWORD:-secret}"          # ユーザーパスワード
      MYSQL_USER: "${DB_USERNAME:-root}"                 # ユーザー名
```

### 設定の変更方法

1. `.env`ファイルで環境変数を変更
2. `docker-compose.yml`を直接編集

**注意：** 設定を変更した後は、コンテナを再起動してください：

```bash
docker-compose down
docker-compose up -d mysql
```

---

## トラブルシューティング

### エラー: "Cannot connect to MySQL server"

**原因：** コンテナが起動していない、または起動中

**解決方法：**
```bash
# コンテナの状態を確認
docker-compose ps

# コンテナを起動
docker-compose up -d mysql

# コンテナが完全に起動するまで待つ（約10-20秒）
sleep 10

# 再度接続を試す
php artisan migrate --pretend
```

### エラー: "Access denied for user"

**原因：** ユーザー名またはパスワードが間違っている

**解決方法：**
- `.env`ファイルの`DB_USERNAME`と`DB_PASSWORD`を確認
- `docker-compose.yml`の環境変数を確認

### エラー: "Unknown database"

**原因：** データベースが作成されていない

**解決方法：**
```bash
# コンテナ内でデータベースを作成
docker-compose exec mysql mysql -u root -psecret -e "CREATE DATABASE IF NOT EXISTS laravel_db;"
```

### ポート3306が既に使用されている

**原因：** ローカルのMySQLサーバーが起動している

**解決方法：**
1. ローカルのMySQLサーバーを停止
2. または、`docker-compose.yml`でポートを変更：
   ```yaml
   ports:
     - "3307:3306"  # 3307ポートに変更
   ```
3. `.env`ファイルの`DB_PORT`も変更：
   ```env
   DB_PORT=3307
   ```

### コンテナが起動しない

**原因：** ポートの競合、メモリ不足、設定エラー

**解決方法：**
```bash
# ログを確認
docker-compose logs mysql

# コンテナを再作成
docker-compose down
docker-compose up -d mysql

# Dockerの状態を確認
docker ps -a
docker system df
```

### データベースのデータが消えた

**原因：** `docker-compose down -v`を実行した

**解決方法：**
- データはボリュームに保存されているため、通常は`down`コマンドでは消えません
- `-v`オプションを使用した場合のみ、データが削除されます
- バックアップを取ることを推奨します

---

## データのバックアップとリストア

### バックアップ

```bash
# データベース全体をバックアップ
docker-compose exec mysql mysqldump -u root -psecret laravel_db > backup_$(date +%Y%m%d_%H%M%S).sql

# 特定のテーブルのみバックアップ
docker-compose exec mysql mysqldump -u root -psecret laravel_db oauth_clients users > tables_backup.sql
```

### リストア

```bash
# バックアップからリストア
docker-compose exec -T mysql mysql -u root -psecret laravel_db < backup_20231214_120000.sql
```

---

## よく使うコマンド一覧

```bash
# コンテナの起動
docker-compose up -d mysql

# コンテナの停止
docker-compose stop mysql

# コンテナの再起動
docker-compose restart mysql

# コンテナの状態確認
docker-compose ps

# ログの確認
docker-compose logs -f mysql

# MySQLに接続
docker-compose exec mysql mysql -u root -psecret laravel_db

# データベースのバックアップ
docker-compose exec mysql mysqldump -u root -psecret laravel_db > backup.sql

# コンテナとデータの削除（注意：データも削除されます）
docker-compose down -v
```

---

## 参考リソース

- [Docker公式ドキュメント](https://docs.docker.com/)
- [Docker Compose公式ドキュメント](https://docs.docker.com/compose/)
- [MySQL公式ドキュメント](https://dev.mysql.com/doc/)
- [Laravel Database Documentation](https://laravel.com/docs/database)

