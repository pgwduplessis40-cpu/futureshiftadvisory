<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Operations\DeploymentIdentity;
use Illuminate\Http\JsonResponse;

final class DeploymentController extends Controller
{
    public function __invoke(DeploymentIdentity $deployment): JsonResponse
    {
        $payload = $deployment->current();
        $verified = $payload['status'] === 'verified';

        return response()
            ->json($payload, $verified ? 200 : 503)
            ->header('Cache-Control', 'no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('X-FSA-Deployment-Status', $payload['status'])
            ->header('X-FSA-Release', $payload['version'] ?? 'unverified')
            ->header('X-FSA-Commit', $payload['commit'] ?? 'unverified');
    }
}
