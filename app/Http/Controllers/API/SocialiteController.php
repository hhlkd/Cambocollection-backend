<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirectToProvider($provider)
{
    return Socialite::driver($provider)->redirect();
}

    public function handleProviderCallback($provider)
    {
        try {
        $socialUser = Socialite::driver($provider)->user();
    } catch (\Exception $e) {
        return redirect()->to('/auth?error=social_login_failed');
    }

        $user = User::where('email', $socialUser->getEmail())->first();
        if (!$user) {
            $user = User::create([
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'password' => bcrypt(Str::random(24)),
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $payload = base64_encode(json_encode([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin ?? false,
        ]));
        return redirect()->to($frontendUrl . "/auth/social-callback?token={$token}&user={$payload}");
    }
}