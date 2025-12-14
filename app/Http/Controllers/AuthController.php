<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ユーザーログイン（認証情報の検証のみ）
     * OAuth2.0の認証コード取得の前段階として使用
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => '認証情報が正しくありません',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => '認証情報が正しくありません',
            ], 401);
        }

        return response()->json([
            'message' => '認証に成功しました',
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'note' => 'OAuth2.0認証コードを取得するには /oauth/authorize エンドポイントを使用してください',
        ], 200);
    }

    /**
     * 認証済みユーザー情報を取得
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'email_verified_at' => $request->user()->email_verified_at,
            'created_at' => $request->user()->created_at,
        ], 200);
    }

    /**
     * ログアウト（現在のアクセストークンを無効化）
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return response()->json([
            'message' => 'ログアウトしました',
        ], 200);
    }

    /**
     * ログイン画面を表示（Web用）
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * ユーザーログイン（Web用、セッション認証）
     * OAuth2.0認証コード取得のためのログイン
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function webLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // OAuth2.0認証フローの場合、元のURL（/oauth/authorize）にリダイレクト
            // Laravelの認証例外ハンドラーが自動的にセッションに保存したURLを使用
            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => '認証情報が正しくありません。',
        ])->withInput();
    }

    /**
     * ログアウト（Web用、セッション認証）
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function webLogout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * OAuth2.0認証コード受け取り用のコールバックエンドポイント
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');
        $errorDescription = $request->query('error_description');

        if ($error) {
            return view('auth.oauth.callback', [
                'error' => $error,
                'error_description' => $errorDescription,
                'code' => null,
                'state' => $state,
            ]);
        }

        if (!$code) {
            return view('auth.oauth.callback', [
                'error' => 'no_code',
                'error_description' => '認証コードが取得できませんでした',
                'code' => null,
                'state' => $state,
            ]);
        }

        return view('auth.oauth.callback', [
            'code' => $code,
            'state' => $state,
            'error' => null,
            'error_description' => null,
        ]);
    }
}

