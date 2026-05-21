<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Services\Integration\Ird\Contracts\IrdClient;
use Tests\TestCase;

final class IrdTest extends TestCase
{
    public function test_stub_returns_canned_gst_status(): void
    {
        $result = app(IrdClient::class)->gstStatus('9429000000000');

        $this->assertSame('9429000000000', $result['nzbn']);
        $this->assertSame('123456789', $result['ird_number']);
        $this->assertTrue($result['gst_registered']);
        $this->assertSame('2024-04-02', $result['gst_effective_from']);
        $this->assertSame('stub', $result['source_badge']);
        $this->assertFalse($result['degraded']);
    }
}
