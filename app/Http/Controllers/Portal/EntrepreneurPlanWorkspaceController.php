<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurPlanWorkspaceController extends Controller
{
    public function __construct(
        private readonly EntrepreneurPlanWorkspace $workspace,
        private readonly EntrepreneurPlanWorkspacePayload $payload,
    ) {}

    public function show(Request $request): Response
    {
        $profile = $this->workspace->profileFor($this->workspace->user($request));

        return Inertia::render('portal/entrepreneur/Plan', $this->payload->show($profile));
    }
}
