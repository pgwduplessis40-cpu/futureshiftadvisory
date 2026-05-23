<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Receipt;
use App\Services\Audit\AuditWriter;
use App\Services\Pdf\PdfRenderer;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ReceiptGenerator
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
    ) {}

    public function create(Payment $payment): Receipt
    {
        $payment = $payment->refresh()->loadMissing(['client', 'paymentSchedule', 'paymentAuthority']);

        if ($payment->status !== Payment::STATUS_SUCCEEDED) {
            throw new RuntimeException('Only succeeded payments can receive receipts.');
        }

        if ($payment->receipt()->exists()) {
            return $payment->receipt()->firstOrFail();
        }

        $pdf = $this->renderer->render($this->html($payment));
        $path = $this->path($payment);

        if (Storage::disk('secure_local')->put($path, $pdf) !== true) {
            throw new RuntimeException('Payment receipt PDF could not be stored.');
        }

        $hashEnvelope = $this->envelope->encrypt(hash('sha256', $pdf));

        $receipt = Receipt::query()->create([
            'client_id' => $payment->client_id,
            'payment_id' => $payment->getKey(),
            'receipt_path' => $path,
            'receipt_sha256_envelope' => $hashEnvelope,
            'receipt_envelope_meta' => $this->envelope->inspect($hashEnvelope),
            'receipt_byte_size' => strlen($pdf),
            'generated_at' => now(),
        ]);

        $this->audit->record('payment.receipt_generated', subject: $payment, after: [
            'receipt_id' => $receipt->getKey(),
            'receipt_path' => $path,
            'receipt_byte_size' => $receipt->receipt_byte_size,
        ]);

        return $receipt->refresh();
    }

    private function path(Payment $payment): string
    {
        return sprintf(
            'payments/receipts/%s/%s/%s-receipt.pdf',
            $payment->client_id,
            now()->format('Y/m'),
            Str::uuid(),
        );
    }

    private function html(Payment $payment): string
    {
        $client = $payment->client;

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Payment receipt</title>
<style>
body { color: #17211b; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.55; margin: 0; }
.brand { border-bottom: 2px solid #2f6f5e; margin-bottom: 18px; padding-bottom: 12px; }
.brand h1 { font-size: 22px; margin: 0 0 4px; }
.meta { background: #f4f7f5; border: 1px solid #d8e2dc; margin-bottom: 22px; padding: 12px; }
.meta p { margin: 0 0 5px; }
.meta strong { display: inline-block; min-width: 128px; }
</style>
</head>
<body>
<header class="brand">
<h1>Future Shift Advisory</h1>
<p>Payment receipt</p>
</header>
<section class="meta">
<p><strong>Client</strong> %s</p>
<p><strong>Payment ID</strong> %s</p>
<p><strong>Amount</strong> %s %s</p>
<p><strong>Gateway</strong> %s</p>
<p><strong>Gateway ref</strong> %s</p>
<p><strong>Processed at</strong> %s</p>
</section>
<p>This receipt confirms the payment attempt recorded by Future Shift Advisory.</p>
</body>
</html>
HTML,
            $this->escape($client?->legal_name ?? 'Unknown client'),
            $this->escape((string) $payment->getKey()),
            $this->escape($payment->currency),
            $this->escape((string) $payment->amount),
            $this->escape((string) $payment->gateway),
            $this->escape((string) $payment->gateway_ref),
            $this->escape($payment->processed_at?->toIso8601String() ?? now()->toIso8601String()),
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
