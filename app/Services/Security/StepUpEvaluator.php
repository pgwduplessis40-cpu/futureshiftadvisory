<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class StepUpEvaluator
{
    public const SESSION_LAST_ACTIVITY_AT = 'fsa.session.last_activity_at';

    public const SESSION_DEVICE_FINGERPRINT = 'fsa.session.device_fingerprint';

    public const SESSION_IP_ADDRESS = 'fsa.session.ip_address';

    public const SESSION_COUNTRY = 'fsa.session.country';

    public const SESSION_USER_AGENT = 'fsa.session.user_agent';

    public function timeoutMinutes(User $user): int
    {
        if (is_int($user->session_timeout_minutes) && $user->session_timeout_minutes > 0) {
            return $user->session_timeout_minutes;
        }

        $timeouts = config('security.session_timeouts', []);
        $role = $user->fsaRole();

        return max(1, (int) ($timeouts[$role] ?? $timeouts[$user->user_type] ?? $timeouts['default'] ?? 30));
    }

    public function sessionExpired(Request $request, User $user): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $lastActivityAt = $request->session()->get(self::SESSION_LAST_ACTIVITY_AT);
        if (! is_numeric($lastActivityAt)) {
            return false;
        }

        return Date::createFromTimestamp((int) $lastActivityAt)
            ->addMinutes($this->timeoutMinutes($user))
            ->isPast();
    }

    public function touchSession(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_LAST_ACTIVITY_AT, Date::now()->getTimestamp());
        }
    }

    public function evaluate(Request $request, User $user): StepUpAssessment
    {
        if (! $request->hasSession()) {
            return new StepUpAssessment(0, $this->threshold(), []);
        }

        $score = 0;
        $signals = [];
        $session = $request->session();

        $currentIp = (string) $request->ip();
        $currentCountry = $this->country($request);
        $currentUserAgent = (string) $request->userAgent();
        $currentFingerprint = $this->fingerprint($currentIp, $currentCountry, $currentUserAgent);

        $previousIp = $session->get(self::SESSION_IP_ADDRESS);
        $previousCountry = $session->get(self::SESSION_COUNTRY);
        $previousUserAgent = $session->get(self::SESSION_USER_AGENT);
        $previousFingerprint = $session->get(self::SESSION_DEVICE_FINGERPRINT);

        if (is_string($previousIp) && $previousIp !== '' && $previousIp !== $currentIp) {
            $score += $this->signalScore('ip_changed');
            $signals['ip_changed'] = true;
        }

        if (
            is_string($previousCountry)
            && $previousCountry !== ''
            && $currentCountry !== ''
            && $previousCountry !== $currentCountry
        ) {
            $score += $this->signalScore('country_changed');
            $signals['country_changed'] = true;
        }

        if (is_string($previousUserAgent) && $previousUserAgent !== '' && $previousUserAgent !== $currentUserAgent) {
            $score += $this->signalScore('user_agent_changed');
            $signals['user_agent_changed'] = true;
        }

        $newDevice = is_string($previousFingerprint)
            && $previousFingerprint !== ''
            && $previousFingerprint !== $currentFingerprint;

        if ($this->isSuperAdminRoute($request) && $newDevice) {
            $score += $this->signalScore('super_admin_route_new_device');
            $signals['super_admin_route_new_device'] = true;
        }

        $session->put([
            self::SESSION_IP_ADDRESS => $currentIp,
            self::SESSION_COUNTRY => $currentCountry,
            self::SESSION_USER_AGENT => $currentUserAgent,
            self::SESSION_DEVICE_FINGERPRINT => $currentFingerprint,
        ]);

        return new StepUpAssessment($score, $this->threshold(), $signals);
    }

    public function rememberDevice(Request $request): void
    {
        $request->session()->put([
            self::SESSION_IP_ADDRESS => (string) $request->ip(),
            self::SESSION_COUNTRY => $this->country($request),
            self::SESSION_USER_AGENT => (string) $request->userAgent(),
            self::SESSION_DEVICE_FINGERPRINT => $this->fingerprint(
                (string) $request->ip(),
                $this->country($request),
                (string) $request->userAgent(),
            ),
        ]);
    }

    public function syncSessionRecord(Request $request, StepUpAssessment $assessment, bool $requiresStepUp): void
    {
        if (! $request->hasSession() || config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('id', $request->session()->getId())
            ->update([
                'risk_score' => $assessment->score,
                'step_up_at' => $requiresStepUp ? now() : null,
            ]);
    }

    private function threshold(): int
    {
        return max(1, (int) config('security.step_up.threshold', 70));
    }

    private function signalScore(string $signal): int
    {
        return max(0, (int) config("security.step_up.signals.{$signal}", 0));
    }

    private function country(Request $request): string
    {
        $country = $request->headers->get('CF-IPCountry')
            ?? $request->headers->get('X-Country-Code')
            ?? '';

        return strtoupper(substr((string) $country, 0, 2));
    }

    private function fingerprint(string $ip, string $country, string $userAgent): string
    {
        return hash('sha256', implode('|', [$ip, $country, $userAgent]));
    }

    private function isSuperAdminRoute(Request $request): bool
    {
        return $request->routeIs('admin.*') || str_starts_with(trim($request->path(), '/'), 'admin/');
    }
}
