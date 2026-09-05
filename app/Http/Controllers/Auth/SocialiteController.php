<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OAuthState;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    /**
     * Redirect to Google OAuth
     * Store the tenant host in database keyed by Socialite's state parameter
     */
    public function redirect(): RedirectResponse
    {
        $tenantHost = request()->getHost();

        Log::info('OAuth redirect initiated', [
            'tenant_host' => $tenantHost,
            'full_url' => request()->url(),
        ]);

        // Socialite will generate a state parameter and pass it to Google
        // We'll intercept it and store the tenant host for later retrieval
        // Get the Socialite provider
        $provider = Socialite::driver('google');

        // Build the redirect URL manually to capture the state
        $redirectUrl = $provider->redirect()->getTargetUrl();

        // Extract the state parameter from the redirect URL
        $urlParts = parse_url($redirectUrl);
        parse_str($urlParts['query'], $queryParams);
        $state = $queryParams['state'] ?? null;

        if ($state) {
            Log::info('Captured Socialite state', ['state' => $state]);

            // Store the tenant host keyed by this state
            OAuthState::create([
                'token' => $state,
                'tenant_host' => $tenantHost,
                'expires_at' => now()->addMinutes(10),
            ]);

            Log::info('Stored tenant in database with state', [
                'state' => $state,
                'tenant_host' => $tenantHost,
            ]);
        }

        // Return the original redirect
        return redirect()->away($redirectUrl);
    }

    /**
     * Handle Google OAuth callback
     * Retrieve tenant host from database using Socialite's state parameter
     */
    public function callback()
    {
        $state = request()->get('state');

        Log::info('OAuth callback received', [
            'request_host' => request()->getHost(),
            'state' => $state,
        ]);

        try {
            // Default to current host
            $tenantHost = request()->getHost();

            // Look up the tenant host using the state parameter
            if ($state) {
                $oauthState = OAuthState::where('token', $state)->first();

                if ($oauthState) {
                    $tenantHost = $oauthState->tenant_host;
                    Log::info('Retrieved tenant from database', [
                        'state' => $state,
                        'tenant_host' => $tenantHost,
                    ]);

                    // Clean up
                    $oauthState->delete();
                } else {
                    Log::warning('OAuth state not found in database', ['state' => $state]);
                }
            } else {
                Log::warning('No state parameter received from Google');
            }

            $googleUser = Socialite::driver('google')->user();

            Log::info('Google user authenticated', [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
            ]);

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'email_verified_at' => now(),
                    'password' => bcrypt(''),
                ]
            );

            Log::info('User created or updated', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            Auth::login($user, remember: true);

            Log::info('User logged in', [
                'user_id' => Auth::id(),
            ]);

            // Build the protocol (http/https)
            $protocol = request()->secure() ? 'https' : 'http';
            $dashboardUrl = "{$protocol}://{$tenantHost}/dashboard";

            Log::info('Constructed dashboard URL', [
                'protocol' => $protocol,
                'tenant_host' => $tenantHost,
                'dashboard_url' => $dashboardUrl,
            ]);

            return redirect($dashboardUrl);
        } catch (\Exception $e) {
            Log::error('OAuth callback failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')->withErrors(['google' => 'Failed to authenticate with Google']);
        }
    }
}
