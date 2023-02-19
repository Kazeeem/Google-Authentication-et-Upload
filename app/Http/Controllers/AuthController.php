<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * The packages used are
     * composer require laravel/socialite
     * composer require google/apiclient
     *
     * Enable Drive API and Storage Transfer API
     */

    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/drive.file'])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent select_account'
            ])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        $user = Socialite::driver('google')->stateless()->user();

        // Store the user's Google Drive access token in the session
        $request->session()->put('google_access_token', $user->token);

        $existingUser = User::where('email', $user->email)->first();

        if ($existingUser) {
            auth()->login($existingUser, true);
        }
        else {
            $newUser = User::create([
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => now(),
                'google_id' => $user->id
            ]);

            auth()->login($newUser, true);
        }

        // Redirect to your dashboard
        return redirect('/upload');
    }
}
