<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ConnectionAccessController extends Controller
{
    private const OTP_SESSION_KEY = 'connection_login_otp';
    private const OTP_LIFETIME_MINUTES = 10;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_SEND_MAX_ATTEMPTS = 5;
    private const OTP_SEND_DECAY_SECONDS = 600;

    public function login(Request $request): View|RedirectResponse
    {
        if ($this->authenticatedAdmin($request)) {
            return redirect()->route('connection.dashboard');
        }

        if ($request->boolean('reset')) {
            $this->clearOtpChallenge($request);
        }

        return view('connection-login', [
            'frontendUrl' => rtrim((string) config('services.frontend.url'), '/'),
            'error' => session('error'),
            'status' => session('status'),
            'awaitingOtp' => $this->hasPendingOtpChallenge($request),
            'challengeEmail' => $this->maskEmail((string) data_get($request->session()->get(self::OTP_SESSION_KEY), 'email', '')),
            'challengeExpiresIn' => $this->otpExpiresInMinutes($request),
        ]);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        if (! $this->smtpConfigured()) {
            Log::warning('Connection analytics OTP mail is not configured.', $this->mailDebugContext($request, [
                'requested_email' => strtolower($validated['email']),
            ]));

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'OTP mail is not configured on the backend.');
        }

        $admin = $this->findActiveAdminByEmail($validated['email']);

        if (! $admin) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'This email is not allowed to open analytics.');
        }

        $sendLimiterKey = $this->otpSendLimiterKey($request, $admin->email);

        if (RateLimiter::tooManyAttempts($sendLimiterKey, self::OTP_SEND_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($sendLimiterKey);

            Log::warning('Connection analytics OTP request rate limited.', $this->mailDebugContext($request, [
                'admin_id' => $admin->id,
                'admin_email' => strtolower($admin->email),
                'retry_after_seconds' => $seconds,
            ]));

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Too many code requests. Try again in '.max(1, (int) ceil($seconds / 60)).' minute(s).');
        }

        $otp = (string) random_int(100000, 999999);

        try {
            Log::info('Connection analytics OTP send starting.', $this->mailDebugContext($request, [
                'admin_id' => $admin->id,
                'admin_email' => strtolower($admin->email),
            ]));

            Mail::raw(
                "Your Connection analytics login code is {$otp}. It expires in "
                .self::OTP_LIFETIME_MINUTES
                ." minutes.",
                function ($message) use ($admin): void {
                    $message
                        ->to($admin->email, $admin->name ?: null)
                        ->subject('Connection analytics login code');
                }
            );

            Log::info('Connection analytics OTP send completed.', $this->mailDebugContext($request, [
                'admin_id' => $admin->id,
                'admin_email' => strtolower($admin->email),
            ]));
        } catch (Throwable $exception) {
            Log::error('Connection analytics OTP send failed.', $this->mailDebugContext($request, [
                'admin_id' => $admin->id,
                'admin_email' => strtolower($admin->email),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'exception_code' => $exception->getCode(),
                'previous_exception_class' => $exception->getPrevious() ? $exception->getPrevious()::class : null,
                'previous_exception_message' => $exception->getPrevious()?->getMessage(),
                'exception' => $exception,
            ]));

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'The login code could not be sent right now.');
        }

        RateLimiter::hit($sendLimiterKey, self::OTP_SEND_DECAY_SECONDS);

        $request->session()->put(self::OTP_SESSION_KEY, [
            'admin_id' => $admin->id,
            'email' => $admin->email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(self::OTP_LIFETIME_MINUTES)->toIso8601String(),
            'attempts' => 0,
        ]);

        return redirect()
            ->route('connection.login')
            ->with('status', 'A 6-digit code was sent to '.$this->maskEmail($admin->email).'.');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => 'required|digits:6',
        ]);

        $challenge = $request->session()->get(self::OTP_SESSION_KEY);

        if (! is_array($challenge) || ! filled($challenge['email'] ?? null) || ! filled($challenge['otp_hash'] ?? null)) {
            return redirect()
                ->route('connection.login')
                ->with('error', 'Request a new login code first.');
        }

        if ($this->challengeExpired($challenge)) {
            $this->clearOtpChallenge($request);

            return redirect()
                ->route('connection.login')
                ->with('error', 'That code expired. Request a new one.');
        }

        $attempts = (int) ($challenge['attempts'] ?? 0) + 1;
        $challenge['attempts'] = $attempts;
        $request->session()->put(self::OTP_SESSION_KEY, $challenge);

        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            $this->clearOtpChallenge($request);

            return redirect()
                ->route('connection.login')
                ->with('error', 'Too many wrong codes. Request a new one.');
        }

        if (! Hash::check($validated['otp'], (string) $challenge['otp_hash'])) {
            return back()->with('error', 'That code is not correct.');
        }

        $admin = User::query()
            ->where('id', $challenge['admin_id'])
            ->where('email', $challenge['email'])
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();

        if (! $admin) {
            $this->clearOtpChallenge($request);

            return redirect()
                ->route('connection.login')
                ->with('error', 'This account is not allowed to open analytics.');
        }

        $this->clearOtpChallenge($request);
        $request->session()->regenerate();
        $request->session()->put([
            'connection_admin_id' => $admin->id,
            'connection_admin_email' => $admin->email,
        ]);

        return redirect()->route('connection.dashboard');
    }

    public function resetOtp(Request $request): RedirectResponse
    {
        $this->clearOtpChallenge($request);

        return redirect()->route('connection.login');
    }

    public function access(Request $request, FirebaseIdentityService $firebaseIdentity): RedirectResponse
    {
        $validated = $request->validate([
            'id_token' => 'required|string',
        ]);

        $admin = $this->authenticateAdminToken($request, $firebaseIdentity, $validated['id_token']);

        if (! $admin) {
            return redirect()
                ->route('connection.login')
                ->with('error', session('error', 'Could not verify your frontend admin login.'));
        }

        return redirect()->route('connection.dashboard');
    }

    public function session(Request $request, FirebaseIdentityService $firebaseIdentity): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => 'required|string',
        ]);

        $admin = $this->authenticateAdminToken($request, $firebaseIdentity, $validated['id_token']);

        if (! $admin) {
            return response()->json([
                'message' => session('error', 'Could not verify your frontend admin login.'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'admin' => [
                'id' => $admin->id,
                'email' => $admin->email,
            ],
        ]);
    }

    public function logout(Request $request): RedirectResponse|JsonResponse
    {
        $request->session()->forget([
            'connection_admin_id',
            'connection_admin_email',
        ]);
        $this->clearOtpChallenge($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()->route('connection.login');
    }

    public static function authenticatedAdmin(Request $request): ?User
    {
        $adminId = $request->session()->get('connection_admin_id');
        $adminEmail = $request->session()->get('connection_admin_email');

        if (! $adminId || ! $adminEmail) {
            return null;
        }

        return User::query()
            ->where('id', $adminId)
            ->where('email', $adminEmail)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();
    }

    private function authenticateAdminToken(
        Request $request,
        FirebaseIdentityService $firebaseIdentity,
        string $idToken
    ): ?User {
        if (! $firebaseIdentity->isConfigured()) {
            $request->session()->flash('error', 'Frontend admin sign-in is not configured on the backend.');

            return null;
        }

        $identity = $firebaseIdentity->lookupByIdToken($idToken);

        return $this->authenticateAdminIdentity($request, $identity);
    }

    private function authenticateAdminIdentity(Request $request, ?array $identity): ?User
    {
        if (! $identity || ! filled($identity['email'] ?? null)) {
            $request->session()->flash('error', 'Could not verify your admin login.');

            return null;
        }

        $admin = User::query()
            ->where('email', strtolower((string) $identity['email']))
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();

        if (! $admin) {
            $request->session()->flash('error', 'This account is not allowed to open analytics.');

            return null;
        }

        $this->clearOtpChallenge($request);
        $request->session()->regenerate();
        $request->session()->put([
            'connection_admin_id' => $admin->id,
            'connection_admin_email' => $admin->email,
        ]);

        return $admin;
    }

    private function findActiveAdminByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', strtolower($email))
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();
    }

    private function smtpConfigured(): bool
    {
        return filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.mailers.smtp.username'))
            && filled(config('mail.mailers.smtp.password'))
            && config('mail.default') === 'smtp';
    }

    private function hasPendingOtpChallenge(Request $request): bool
    {
        $challenge = $request->session()->get(self::OTP_SESSION_KEY);

        return is_array($challenge)
            && filled($challenge['email'] ?? null)
            && filled($challenge['otp_hash'] ?? null)
            && ! $this->challengeExpired($challenge);
    }

    private function otpExpiresInMinutes(Request $request): ?int
    {
        $challenge = $request->session()->get(self::OTP_SESSION_KEY);

        if (! is_array($challenge) || ! filled($challenge['expires_at'] ?? null)) {
            return null;
        }

        $remainingSeconds = now()->diffInSeconds($challenge['expires_at'], false);

        if ($remainingSeconds <= 0) {
            return null;
        }

        return max(1, (int) ceil($remainingSeconds / 60));
    }

    private function challengeExpired(array $challenge): bool
    {
        if (! filled($challenge['expires_at'] ?? null)) {
            return true;
        }

        return now()->greaterThanOrEqualTo($challenge['expires_at']);
    }

    private function clearOtpChallenge(Request $request): void
    {
        $request->session()->forget(self::OTP_SESSION_KEY);
    }

    private function otpSendLimiterKey(Request $request, string $email): string
    {
        return 'connection-login-send:'.Str::lower($email).':'.$request->ip();
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$name, $domain] = explode('@', $email, 2);
        $visible = substr($name, 0, min(2, strlen($name)));

        return $visible.str_repeat('*', max(strlen($name) - strlen($visible), 1)).'@'.$domain;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function mailDebugContext(Request $request, array $extra = []): array
    {
        return array_merge([
            'request_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'mailer' => config('mail.default'),
            'smtp_scheme' => config('mail.mailers.smtp.scheme'),
            'smtp_host' => config('mail.mailers.smtp.host'),
            'smtp_port' => config('mail.mailers.smtp.port'),
            'smtp_username' => config('mail.mailers.smtp.username'),
            'from_address' => config('mail.from.address'),
        ], $extra);
    }
}
