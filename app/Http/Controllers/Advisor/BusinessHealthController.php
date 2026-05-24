<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Dashboards\BusinessHealthSnapshotWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class BusinessHealthController extends Controller
{
    public function recompute(
        Request $request,
        string $client,
        BusinessHealthSnapshotWriter $writer,
        AuditWriter $audit,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $client = Client::query()->findOrFail($client);

        if ($user->user_type !== User::TYPE_SUPER_ADMIN) {
            abort_unless(in_array((string) $client->getKey(), $user->accessibleClientIds(), true), 404);
        }

        Gate::authorize('update', $client);

        $snapshots = $writer->recompute($client);
        $batchId = (string) $snapshots->first()?->assessment_batch_id;

        $audit->record('business_health.recomputed', subject: $client, actor: $user, after: [
            'assessment_batch_id' => $batchId,
            'dimensions' => $snapshots
                ->pluck('dimension')
                ->values()
                ->all(),
        ]);

        return back()->with('status', 'business-health-recomputed');
    }
}
