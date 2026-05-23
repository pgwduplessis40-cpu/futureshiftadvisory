<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Communications\BulkCommunicationService;
use App\Support\RequestContext;
use Illuminate\Http\Response;

final class BulkCommunicationOpenController extends Controller
{
    public function __construct(private readonly BulkCommunicationService $communications) {}

    public function __invoke(string $token, RequestContext $context): Response
    {
        $context->apply('system', []);
        $this->communications->trackOpen($token);

        return response(base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw=='), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
