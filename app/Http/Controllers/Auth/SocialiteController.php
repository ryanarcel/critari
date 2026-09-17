<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OAuthState;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Stancl\Tenancy\Facades\Tenancy;

class SocialiteController extends Controller
{
    /**
     * Redirect to Google OAuth
     * Store tenant mapping in database with a custom state token
     */
    public function redirect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:teacher,student'],
        ]);

        $tenantHost = $request->getHost();
        $stateToken = Str::random(40);

        OAuthState::create([
            'token' => $stateToken,
            'tenant_host' => $tenantHost,
            'intended_role' => $validated['role'],
            'expires_at' => now()->addHours(1),
        ]);

        return Socialite::driver('google')
            ->with(['state' => $stateToken])
            ->redirect();
    }

    /**
     * Handle Google OAuth callback
     * Look up tenant from OAuthState using the state parameter
     * Initialize tenant context before creating user
     * Use temporary token to pass auth across domain boundary
     */
    public function callback()
    {
        try {
            $state = request()->query('state');
            $oauthState = OAuthState::where('token', $state)->first();

            if (! $oauthState) {
                throw new \Exception('Invalid OAuth state - tenant mapping not found');
            }

            $tenantHost = $oauthState->tenant_host;
            $intendedRole = in_array($oauthState->intended_role, ['teacher', 'student'], true)
                ? $oauthState->intended_role
                : 'teacher';
            $oauthState->delete();

            $subdomain = explode('.', $tenantHost)[0];
            $tenant = Tenant::whereHas('domains', function ($query) use ($subdomain) {
                $query->where('domain', $subdomain);
            })->first();

            if ($tenant) {
                Tenancy::initialize($tenant);
            }

            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::query()->where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'name' => $googleUser->getName(),
                ]);
            } else {
                $user = User::create([
                    'email' => $googleUser->getEmail(),
                    'name' => $googleUser->getName(),
                    'email_verified_at' => now(),
                    'password' => bcrypt(''),
                    'role' => $intendedRole,
                ]);
            }

            Log::info('OAuth callback - user created/updated', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'tenant_id' => $tenant?->id,
                'role' => $user->role,
            ]);

            $token = Str::random(40);
            DB::connection('landlord')->table('o_auth_states')->insert([
                'token' => $token,
                'tenant_host' => json_encode([
                    'user_id' => $user->id,
                    'tenant_id' => $tenant?->id,
                    'intended_role' => $intendedRole,
                ]),
                'intended_role' => $intendedRole,
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $protocol = request()->secure() ? 'https' : 'http';
            $verifyUrl = "{$protocol}://{$tenantHost}/auth/oauth/verify?token={$token}";

            Log::info('OAuth callback - redirecting to verify endpoint', [
                'verify_url' => $verifyUrl,
                'token' => $token,
            ]);

            return redirect($verifyUrl);
        } catch (\Exception $e) {
            Log::error('OAuth callback error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')->withErrors(['google' => 'Failed to authenticate with Google']);
        }
    }

    /**
     * Verify OAuth token and establish session on subdomain
     */
    public function verify()
    {
        try {
            $token = request()->query('token');
            $record = DB::connection('landlord')
                ->table('o_auth_states')
                ->where('token', $token)
                ->first();

            if (! $record || now()->isAfter($record->expires_at)) {
                throw new \Exception('Invalid or expired OAuth token');
            }

            $data = json_decode($record->tenant_host, true);
            $userId = $data['user_id'] ?? null;

            if (! $userId) {
                throw new \Exception('Invalid token data');
            }

            DB::connection('landlord')
                ->table('o_auth_states')
                ->where('token', $token)
                ->delete();

            $user = User::find($userId);
            if (! $user) {
                throw new \Exception('User not found');
            }

            Log::info('OAuth verify - logging in user', [
                'user_id' => $user->id,
                'token' => $token,
            ]);

            Auth::login($user, remember: true);

            session()->save();

            $sessionId = session()->getId();

            Log::info('OAuth verify - session created', [
                'auth_check' => Auth::check(),
                'auth_id' => Auth::id(),
                'session_id' => $sessionId,
                'session_in_db' => DB::connection('landlord')->table('sessions')->where('id', $sessionId)->exists(),
            ]);

            $redirect = $user->role === 'student'
                ? redirect()->route('student.home')
                : redirect()->route('dashboard');

            $intendedRole = $data['intended_role'] ?? $record->intended_role ?? null;

            if ($intendedRole && $intendedRole !== $user->role) {
                $redirect->with('status', 'This Google account is already a '.$user->role.'.');
            }

            return $redirect;
        } catch (\Exception $e) {
            Log::error('OAuth verify error', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('login')->withErrors(['oauth' => 'Authentication failed']);
        }
    }
}
