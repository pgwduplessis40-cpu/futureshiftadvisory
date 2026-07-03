<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use Tests\TestCase;

final class OfflinePwaTest extends TestCase
{
    public function test_app_shell_advertises_manifest_and_service_worker_assets_exist(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/manifest.webmanifest', false)
            ->assertSee('theme-color', false);

        $manifest = $this->jsonFile(public_path('manifest.webmanifest'));
        $this->assertSame('Future Shift Advisory Portal', $manifest['name']);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertContains('/icons/fsa-pwa-192.png', array_column($manifest['icons'], 'src'));
        $this->assertContains('/icons/fsa-pwa-512.png', array_column($manifest['icons'], 'src'));
        $this->assertContains('/icons/fsa-pwa-maskable-512.png', array_column($manifest['icons'], 'src'));

        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('icons/fsa-pwa-192.png'));
        $this->assertFileExists(public_path('icons/fsa-pwa-512.png'));
        $this->assertFileExists(public_path('icons/fsa-pwa-maskable-512.png'));
    }

    public function test_app_shell_includes_google_tag_when_measurement_id_is_configured(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-93DXC5XFNS']);

        $html = view('app', [
            'appearance' => 'light',
            'page' => ['component' => 'home'],
        ])->render();

        $this->assertStringContainsString(
            'https://www.googletagmanager.com/gtag/js?id=G-93DXC5XFNS',
            $html,
        );
        $this->assertStringContainsString('gtag(\'config\', "G-93DXC5XFNS");', $html);
    }

    public function test_service_worker_handles_portal_fallback_and_sync_messages(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        $this->assertIsString($serviceWorker);
        $this->assertStringContainsString("url.pathname.startsWith('/portal')", $serviceWorker);
        $this->assertStringContainsString("url.pathname === '/dashboard'", $serviceWorker);
        $this->assertStringContainsString("caches.match('/offline.html')", $serviceWorker);
        $this->assertStringContainsString("'portal-offline-sync'", $serviceWorker);
        $this->assertStringContainsString('PORTAL_OFFLINE_SYNC', $serviceWorker);
        $this->assertStringContainsString('/icons/fsa-pwa-192.png', $serviceWorker);
    }

    public function test_offline_queue_uses_encrypted_indexeddb_storage_and_dedupe_keys(): void
    {
        $offline = file_get_contents(resource_path('js/lib/portal-offline.ts'));

        $this->assertIsString($offline);
        $this->assertStringContainsString('indexedDB.open', $offline);
        $this->assertStringContainsString('crypto.subtle.encrypt', $offline);
        $this->assertStringContainsString('crypto.subtle.decrypt', $offline);
        $this->assertStringContainsString("store.createIndex('dedupeKey'", $offline);
        $this->assertStringContainsString('queueQuestionnaireSubmission', $offline);
        $this->assertStringContainsString('queueDocumentUpload', $offline);
        $this->assertStringContainsString('flushPortalOfflineQueue', $offline);
        $this->assertStringContainsString('clientId?: string', $offline);
        $this->assertStringContainsString('X-Idempotency-Key', $offline);
        $this->assertStringContainsString('X-Portal-Client-Id', $offline);
        $this->assertStringContainsString('!record.clientId', $offline);
        $this->assertStringContainsString('portal-offline-legacy-record', $offline);
        $this->assertStringContainsString('syncResponseSucceeded', $offline);
        $this->assertStringContainsString('application/json', $offline);
        $this->assertStringContainsString('clearPortalOfflineQueue', $offline);
        $this->assertStringContainsString('indexedDB.deleteDatabase', $offline);
    }

    public function test_portal_forms_queue_questionnaires_and_uploads_when_offline(): void
    {
        $step = file_get_contents(resource_path('js/pages/portal/onboarding/Step.tsx'));
        $renderer = file_get_contents(resource_path('js/components/questionnaires/QuestionnaireRenderer.tsx'));

        $this->assertIsString($step);
        $this->assertIsString($renderer);
        $this->assertStringContainsString('!navigator.onLine', $step);
        $this->assertStringContainsString('queueQuestionnaireSubmission', $step);
        $this->assertStringContainsString('client.id', $step);
        $this->assertStringContainsString('!navigator.onLine', $renderer);
        $this->assertStringContainsString('queueDocumentUpload', $renderer);
        $this->assertStringContainsString('clientId', $renderer);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonFile(string $path): array
    {
        $contents = file_get_contents($path);
        $this->assertIsString($contents);

        $decoded = json_decode($contents, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
