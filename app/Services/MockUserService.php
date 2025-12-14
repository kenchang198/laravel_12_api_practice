<?php

namespace App\Services;

use Illuminate\Support\Collection;

class MockUserService
{
    /**
     * モックユーザーデータを生成
     *
     * @param int $count 生成するユーザー数
     * @return Collection
     */
    public function generateUsers(int $count = 100): Collection
    {
        $faker = \Faker\Factory::create('ja_JP');
        $users = collect();

        for ($i = 1; $i <= $count; $i++) {
            $users->push([
                'id' => $i,
                'name' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
                'created_at' => now()->subDays(rand(0, 30))->format('Y-m-d H:i:s'),
            ]);
        }

        return $users;
    }

    /**
     * IDでソートされたモックユーザーデータを取得
     *
     * @param int $count 生成するユーザー数
     * @return Collection
     */
    public function getUsersSortedById(int $count = 100): Collection
    {
        return $this->generateUsers($count)->sortBy('id')->values();
    }
}

