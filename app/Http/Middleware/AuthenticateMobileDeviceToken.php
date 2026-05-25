<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeviceRegistration;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Security\MfaChallenger;
use App\Services\Terms\TermsAcceptanceGate;
use App\Support\RequestContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateMobileDeviceToken
{
    public function __construct(
        private readonly MfaChallenger $mfa,
        private readonly TermsAcceptanceGate $terms,
        private readonly RequestContext $context,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        if ($token === '') {
            return $this->unauthorized('Missing mobile bearer token.');
        }

        $tokenHash = hash('sha256', $token);
        $registration = DeviceRegistration::query()
            ->with('user')
            ->where('token_hash', $tokenHash)
            ->where('status', DeviceRegistration::STATUS_ACTIVE)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $registration instanceof DeviceRegistration || ! $registration->user instanceof User) {
            return $this->unauthorized('Invalid mobile bearer token.');
        }

        $user = $registration->user;
        if ($user->suspended_at !== null) {
            return $this->forbidden('User account is suspended.');
        }

        if (! $this->mfa->hasCompletedEnrolment($user)) {
            return $this->forbidden('MFA enrolment is required for mobile access.');
        }

        if ($this->terms->hasDeclinedTermsSuspension($user)) {
            return $this->forbidden('Terms have been declined.');
        }

        if ($this->terms->requiresAcceptance($user)) {
            return response()->json(['message' => 'Current terms must be accepted before mobile access.'], 428);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn (): User => $user);

        $role = $this->context->resolveRole($user);
        $clientIds = $this->context->resolveClientIds($user);
        $this->context->apply($role, $clientIds, (string) $user->getAuthIdentifier());

        $request->attributes->set('mobile_device', $registration);
        $request->attributes->set('mobile_token_hash', $tokenHash);

        $response = $next($request);

        $registration->forceFill(['last_used_at' => now()])->save();
        $this->audit->record('mobile_api.call', subject: $registration, actor: $user, after: [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
        ]);

        return $response;
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 401);
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 403);
    }
}
