<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

class OAuthClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 既存のクライアントを削除（テスト用）
        Client::where('name', 'Hands-on Test Client')->delete();

        // Authorization Code Grant用のクライアントを作成
        // ハンズオン用：完全に固定のClient Secretを使用（本番環境では使用しないこと）
        $clientSecret = 'handson-test-secret-12345678901234567890';
        $client = new Client();
        $client->id = Str::uuid()->toString();
        $client->name = 'Hands-on Test Client';
        $client->secret = $clientSecret; // Passportが自動的にハッシュ化して保存
        // redirect_urisとgrant_typesは配列として設定（Eloquentが自動的にJSONに変換）
        // localhostと127.0.0.1の両方を許可（開発環境用）
        $client->redirect_uris = [
            'http://localhost:8000/callback',
            'http://127.0.0.1:8000/callback',
        ];
        $client->grant_types = ['authorization_code'];
        $client->revoked = false;
        $client->save();

        $this->command->info('OAuth2.0クライアントが作成されました:');
        $this->command->info('Client ID: ' . $client->id);
        $this->command->info('Client Secret: ' . $clientSecret);
        $this->command->warn('⚠️  Client Secretは安全に保管してください。この値は再表示されません。');
    }
}

