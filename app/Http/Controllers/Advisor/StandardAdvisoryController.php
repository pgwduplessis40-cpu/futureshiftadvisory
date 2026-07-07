<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\StandardAdvisory\StandardAdvisoryWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StandardAdvisoryController extends Controller
{
    public function runAnalysis(Request $request, Client $client, StandardAdvisoryWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $workflow->runAnalysis($client, $user);

        return to_route('advisor.clients.show', $client)->with('status', 'standard-advisory-analysis-run');
    }

    public function generatePack(Request $request, Client $client, StandardAdvisoryWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'waiver_reason' => ['nullable', 'string', 'max:1200'],
            'waiver_modules' => ['nullable', 'array'],
            'waiver_modules.*' => ['string', Rule::in($workflow->requiredAnalysisModuleValues())],
        ]);

        try {
            $waiverReason = trim((string) ($validated['waiver_reason'] ?? ''));
            $waiverModules = (array) ($validated['waiver_modules'] ?? []);

            if ($waiverReason !== '' || $waiverModules !== []) {
                $workflow->recordPackWaiver($client, $user, $waiverModules, $waiverReason);
            }

            $workflow->generateAdvisoryPack($client, $user);
        } catch (ValidationException $exception) {
            return to_route('advisor.clients.show', $client)
                ->withErrors($exception->errors())
                ->withInput();
        } catch (Throwable $exception) {
            report($exception);

            return to_route('advisor.clients.show', $client)
                ->withErrors([
                    'standard_advisory' => 'The advisory pack could not be generated. Check the test environment PDF renderer and logs, then try again.',
                ]);
        }

        return to_route('advisor.clients.show', $client)
            ->with('status', 'standard-advisory-pack-generated')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Advisory pack generated.',
            ]);
    }
}
