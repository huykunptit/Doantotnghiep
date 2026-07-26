<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $this->assignDefaultRole($user);
        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Đăng ký thành công. Vui lòng kiểm tra email để xác nhận tài khoản.',
            'email' => $user->email,
            'requires_verification' => true,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();
        if ($user && !$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            return response()->json([
                'message' => 'Email của bạn chưa được xác nhận. Chúng tôi đã gửi lại email xác nhận.',
                'requires_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid email or password',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $this->assignDefaultRole($user);
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login success',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->serializeUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->serializeUser($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout success',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'url', 'max:2048'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->fill([
            'name' => $validated['name'],
            'avatar' => $validated['avatar'] ?? null,
        ]);
        $user->save();

        return response()->json([
            'message' => 'Profile updated',
            'user' => $this->serializeUser($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    public function redirectToGoogle(): RedirectResponse
    {
        /** @var \Laravel\Socialite\Two\AbstractProvider $provider */
        $provider = Socialite::driver('google');

        return $provider->stateless()->redirect();
    }

    public function googleLoginUrl(): JsonResponse
    {
        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            return response()->json([
                'message' => 'Google OAuth chưa được cấu hình. Vui lòng thiết lập GOOGLE_CLIENT_ID và GOOGLE_CLIENT_SECRET trong file .env',
            ], 422);
        }

        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $provider */
            $provider = Socialite::driver('google');

            return Response::json([
                'url' => $provider->stateless()->redirect()->getTargetUrl(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Không thể kết nối Google OAuth: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function handleGoogleCallback(Request $request): JsonResponse|RedirectResponse
    {
        $frontend = rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost:3000')), '/');
        $wantsRedirect = $this->wantsGoogleBrowserRedirect($request);

        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $provider */
            $provider = Socialite::driver('google');
            $googleUser = $provider->stateless()->user();
        } catch (\Throwable $e) {
            if ($wantsRedirect) {
                return redirect($frontend.'/auth/google?error='.urlencode('Google authentication failed: '.$e->getMessage()));
            }

            return response()->json([
                'message' => 'Google authentication failed: '.$e->getMessage(),
            ], 422);
        }

        if (!$googleUser->getEmail()) {
            if ($wantsRedirect) {
                return redirect($frontend.'/auth/google?error='.urlencode('Google account does not provide an email'));
            }

            return response()->json([
                'message' => 'Google account does not provide an email',
            ], 422);
        }

        $user = User::query()->firstOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName() ?: 'Google User',
                'password' => Hash::make(Str::random(32)),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ]
        );

        $user->fill([
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'name' => $googleUser->getName() ?: $user->name,
        ])->save();

        $this->assignDefaultRole($user);
        $token = $user->createToken('auth-token')->plainTextToken;

        // Browser OAuth return: finish on frontend with token (avoids fragile JS code-exchange).
        if ($wantsRedirect) {
            return redirect($frontend.'/auth/google?token='.urlencode($token));
        }

        return response()->json([
            'message' => 'Google login success',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->serializeUser($user),
        ]);
    }

    private function wantsGoogleBrowserRedirect(Request $request): bool
    {
        if ($request->query('format') === 'json') {
            return false;
        }

        $accept = (string) $request->header('Accept', '');

        // XHR/fetch from SPA sends Accept: application/json
        if (str_contains($accept, 'application/json') && ! str_contains($accept, 'text/html')) {
            return false;
        }

        return true;
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json(['message' => __($status)], 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json(['message' => __($status)], 422);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy tài khoản phù hợp.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email này đã được xác nhận.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Đã gửi lại email xác nhận. Vui lòng kiểm tra hộp thư của bạn.']);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::query()->find($id);
        if (!$user) {
            return response()->json(['message' => 'Liên kết xác nhận không hợp lệ.'], 404);
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification())) || !$request->hasValidSignature()) {
            return response()->json(['message' => 'Liên kết xác nhận đã hết hạn hoặc không hợp lệ.'], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Xác nhận email thành công. Bạn có thể đăng nhập ngay bây giờ.',
            'verified' => true,
        ]);
    }

    private function assignDefaultRole(User $user): void
    {
        if (!$user->hasAnyRole(['admin', 'instructor', 'student']) && Role::query()->where('name', 'student')->exists()) {
            $user->assignRole('student');
        }
    }

    private function serializeUser(User $user): array
    {
        $user->loadMissing(['roles', 'institution', 'unit', 'program', 'major', 'specialization', 'cohort']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'role' => $user->roles->pluck('name')->first(),
            'roles' => $user->roles->pluck('name')->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'user_type' => $user->user_type,
            'student_code' => $user->student_code,
            'staff_code' => $user->staff_code,
            'phone' => $user->phone,
            'organization' => [
                'institution_id' => $user->institution_id,
                'institution_name' => $user->institution?->name,
                'unit_id' => $user->unit_id,
                'unit_name' => $user->unit?->name,
            ],
            'academic' => [
                'program_id' => $user->program_id,
                'program_name' => $user->program?->name,
                'major_id' => $user->major_id,
                'major_name' => $user->major?->name,
                'specialization_id' => $user->specialization_id,
                'specialization_name' => $user->specialization?->name,
                'cohort_id' => $user->cohort_id,
                'cohort_name' => $user->cohort?->name,
            ],
        ];
    }
}
