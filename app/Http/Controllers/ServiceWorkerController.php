<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ReleaseVersion;
use Illuminate\Http\Response;

final class ServiceWorkerController
{
    public function __construct(private readonly ReleaseVersion $releaseVersion) {}

    public function __invoke(): Response
    {
        return response()
            ->view('service-worker', [
                'releaseVersion' => $this->releaseVersion->current(),
            ])
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Service-Worker-Allowed', '/');
    }
}
