<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Services\Integration\Ird\Contracts\IrdClient;
use Tests\TestCase;

final class IrdTest extends TestCase
{
    public function test_ird_status_is_regulatory_deferred_not_verified(): void
    {
        $result = app(IrdClient::class)->gstStatus('9429000000000');

        $this->assertSame('9429000000000', $result['nzbn']);
        $this->assertNull($result['ird_number']);
        $this->assertNull($result['gst_registered']);
        $this->assertNull($result['gst_effective_from']);
        $this->assertSame('declined_current_gateway_pending_data_consumer', $result['verification_status']);
        $this->assertSame('Client supplied - not verified with IRD', $result['verification_label']);
        $this->assertSame('client_supplied_not_ird_verified', $result['source_badge']);
        $this->assertStringContainsString('Data Consumer', $result['regulatory_note']);
        $this->assertFalse($result['degraded']);
    }
}
