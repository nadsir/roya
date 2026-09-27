<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Support\IranianMobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge(['mobile' => IranianMobile::normalize($request->input('mobile'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile'],
        ], ['mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است.']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'mobile' => $data['mobile'],
            'role' => 'customer',
            'is_active' => true,
        ]);

        // Include database defaults (role/is_active) in the initial auth response.
        $user->refresh();

        $token = $user->createToken(
            'customer-dashboard',
            ['customer']
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userData($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (
            $user === null
            || ! Hash::check($credentials['password'], $user->password)
            || ! $user->is_active
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        $user->tokens()
            ->where('name', 'customer-dashboard')
            ->delete();

        $token = $user->createToken(
            'customer-dashboard',
            ['customer']
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userData($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->userData($request->user()),
        ]);
    }

    public function sendOtp(Request $request, OtpService $otp)
    {
        $mobile = $otp->normalize($request->input('mobile'));
        $otp->send($mobile);
        return response()->json([
            'message' => 'اگر حساب فعالی با این شماره وجود داشته باشد، کد ارسال می‌شود. در غیر این صورت ابتدا ثبت‌نام کنید.',
            'retry_after' => 60,
            'expires_in' => 120,
        ]);
    }

    public function verifyOtp(Request $request, OtpService $otp)
    {
        $mobile = $otp->normalize($request->input('mobile'));
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']], [
            'code.required' => 'کد تأیید را وارد کنید.',
            'code.regex' => 'کد تأیید باید شش رقم باشد.',
        ]);
        $user = $otp->verify($mobile, $data['code']);
        $user->tokens()->where('name', 'customer-dashboard')->delete();
        $token = $user->createToken('customer-dashboard', ['customer'])->plainTextToken;
        return response()->json(['token' => $token, 'user' => $this->userData($user)]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
            ],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return response()->json([
            'user' => $this->userData($user->fresh()),
        ]);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
        ];
    }
}
