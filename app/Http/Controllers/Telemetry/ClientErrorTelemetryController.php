<?php

declare(strict_types=1);

namespace App\Http\Controllers\Telemetry;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\ClientErrorFingerprintAlert;
use App\Support\Telemetry\ClientErrorEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class ClientErrorTelemetryController extends Controller
{
    public function __invoke(Request $request, ClientErrorFingerprintAlert $alerts): Response
    {
        $this->rejectUnexpectedFields($request);

        /** @var array{release_sha:string,route:string,feature:string,browser:array{family:string,platform:string,viewport:string},error_fingerprint:string,sanitized_stack:string} $validated */
        $validated = $request->validate([
            'release_sha' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'route' => ['required', 'string', 'max:200', 'regex:/^\/[A-Za-z0-9._\/-]*$/'],
            'feature' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'browser' => ['required', 'array:family,platform,viewport'],
            'browser.family' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._ -]+$/'],
            'browser.platform' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._ -]+$/'],
            'browser.viewport' => ['required', 'string', 'max:16', 'regex:/^\d{2,5}x\d{2,5}$/'],
            'error_fingerprint' => ['required', 'string', 'size:8', 'regex:/^[a-f0-9]+$/'],
            'sanitized_stack' => [
                'required',
                'string',
                'max:2000',
                'regex:/\A(?:Error|EvalError|RangeError|ReferenceError|SyntaxError|TypeError|URIError|AggregateError): \[message-redacted\](?:\nat \[frame-redacted\]){0,12}\z/',
                'not_regex:/@|bearer\s|authorization:|password=|token=|secret=|\$\d|\b(?:NZD|USD|AUD)\s?\d/i',
            ],
        ]);

        $alerts->observe(new ClientErrorEvent(
            releaseSha: $validated['release_sha'],
            route: $validated['route'],
            feature: $validated['feature'],
            browser: $validated['browser'],
            errorFingerprint: $validated['error_fingerprint'],
            sanitizedStack: $validated['sanitized_stack'],
        ));

        return response()->noContent();
    }

    private function rejectUnexpectedFields(Request $request): void
    {
        $allowed = [
            'release_sha',
            'route',
            'feature',
            'browser',
            'error_fingerprint',
            'sanitized_stack',
        ];
        $unexpected = array_values(array_diff(array_keys($request->all()), $allowed));

        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'telemetry' => ['Client-error telemetry accepts only its documented six-field contract.'],
            ]);
        }
    }
}
