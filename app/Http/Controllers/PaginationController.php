<?php

namespace App\Http\Controllers;

use App\Services\MockUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PaginationController extends Controller
{
    public function __construct(
        private MockUserService $mockUserService
    ) {
    }

    /**
     * オフセット&リミット方式のページネーション
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function offset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
        ]);

        $limit = $validated['limit'] ?? 10;
        $offset = $validated['offset'] ?? 0;

        // モックデータを取得
        $allUsers = $this->mockUserService->getUsersSortedById();
        $total = $allUsers->count();

        // オフセット&リミットでページネーション
        $users = $allUsers->skip($offset)->take($limit)->values();

        return response()->json([
            'data' => $users,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    /**
     * カーソルベース方式のページネーション
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function cursor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'cursor' => 'sometimes|string',
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $cursor = $validated['cursor'] ?? null;

        // モックデータを取得（IDでソート済み）
        $allUsers = $this->mockUserService->getUsersSortedById();
        $users = $allUsers;

        // カーソルが指定されている場合、その位置から開始
        $startIndex = 0;
        if ($cursor) {
            $decoded = json_decode(base64_decode($cursor), true);
            if (isset($decoded['id'])) {
                $startIndex = $allUsers->search(function ($user) use ($decoded) {
                    return $user['id'] > $decoded['id'];
                });
                if ($startIndex === false) {
                    $startIndex = $allUsers->count();
                }
            }
        }

        // カーソル位置から指定件数を取得
        $paginatedUsers = $users->slice($startIndex, $perPage)->values();

        // 次ページのカーソルを生成
        $nextCursor = null;
        $hasNext = false;
        if ($paginatedUsers->isNotEmpty() && ($startIndex + $perPage) < $users->count()) {
            $lastUser = $paginatedUsers->last();
            $nextCursor = base64_encode(json_encode(['id' => $lastUser['id']]));
            $hasNext = true;
        }

        // 前ページのカーソルを生成
        $prevCursor = null;
        $hasPrev = false;
        if ($startIndex > 0) {
            $firstUser = $paginatedUsers->first();
            if ($firstUser) {
                // 前ページの最後のIDを取得
                $prevIndex = max(0, $startIndex - $perPage);
                $prevUsers = $users->slice($prevIndex, $perPage);
                if ($prevUsers->isNotEmpty()) {
                    $prevLastUser = $prevUsers->last();
                    $prevCursor = base64_encode(json_encode(['id' => $prevLastUser['id']]));
                    $hasPrev = true;
                }
            }
        }

        return response()->json([
            'data' => $paginatedUsers,
            'meta' => [
                'per_page' => $perPage,
                'next_cursor' => $nextCursor,
                'prev_cursor' => $prevCursor,
                'has_next' => $hasNext,
                'has_prev' => $hasPrev,
            ],
        ]);
    }
}

