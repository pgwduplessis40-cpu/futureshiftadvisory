<?php

declare(strict_types=1);

namespace App\Services\Proposals;

use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class SignedProposalEvidenceRenderer
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly ProposalBuilder $proposals,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function renderPdf(Proposal $proposal, User $actor, string $typedName, array $payload, CarbonInterface $signedAt): string
    {
        return $this->renderer->render($this->renderHtml($proposal, $actor, $typedName, $payload, $signedAt));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function renderHtml(Proposal $proposal, User $actor, string $typedName, array $payload, CarbonInterface $signedAt): string
    {
        $proposal = $proposal->refresh()->loadMissing(['client.primaryContact', 'feeCalculation', 'consents', 'createdBy', 'signoffSteps']);
        $proposalHtml = $this->proposals->previewHtml($proposal);
        $certificate = $this->signatureCertificateHtml($proposal, $actor, $typedName, $payload, $signedAt);
        $banner = $this->signatureBannerHtml($proposal, $actor, $signedAt);
        $style = <<<'CSS'
.proposal-signature-stamp {
  background: #f8f5ee;
  border: 1px solid #ded6c7;
  border-left: 5px solid #b8860b;
  color: #13233a;
  font-family: Arial, sans-serif;
  font-size: 11px;
  margin: 0 0 14px;
  padding: 10px 12px;
}
.proposal-signature-certificate {
  background: #f8f5ee;
  border: 1px solid #ded6c7;
  border-left: 5px solid #b8860b;
  break-before: page;
  color: #13233a;
  font-family: Arial, sans-serif;
  font-size: 11.5px;
  line-height: 1.55;
  margin: 0;
  padding: 18px 20px;
}
.proposal-signature-certificate h1 {
  color: #1c2f4a;
  font-size: 22px;
  line-height: 1.15;
  margin: 0 0 7px;
}
.proposal-signature-certificate h2 {
  border-top: 1px solid #ded6c7;
  color: #0d6a5a;
  font-size: 14px;
  margin: 16px 0 6px;
  padding-top: 10px;
}
.proposal-signature-certificate dl {
  display: grid;
  grid-template-columns: 170px 1fr;
  margin: 0;
}
.proposal-signature-certificate dt {
  color: #667282;
  font-size: 9px;
  font-weight: 700;
  padding: 4px 10px 4px 0;
  text-transform: uppercase;
}
.proposal-signature-certificate dd {
  margin: 0;
  padding: 4px 0;
}
CSS;

        return $this->attachSignatureEvidence($proposalHtml, $banner, $certificate, $style);
    }

    private function signatureBannerHtml(Proposal $proposal, User $actor, CarbonInterface $signedAt): string
    {
        return sprintf(
            '<aside class="proposal-signature-stamp"><strong>Signed proposal v%s</strong><br>Signed at %s by %s &lt;%s&gt;.</aside>',
            $proposal->version,
            $this->escape($signedAt->format('j M Y, g:i A T')),
            $this->escape($actor->name),
            $this->escape($actor->email),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signatureCertificateHtml(Proposal $proposal, User $actor, string $typedName, array $payload, CarbonInterface $signedAt): string
    {
        $identity = is_array($payload['identity_verification'] ?? null) ? $payload['identity_verification'] : [];
        $method = $this->stepPayload($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD);
        $authorityPayload = $this->stepPayload($proposal, ProposalSignoffStep::STEP_AUTHORITY);
        $collectionDay = $this->validCollectionDay($authorityPayload['collection_day'] ?? $method['collection_day'] ?? null);
        $termMonths = $this->proposalTermMonths($proposal);
        $monthlyAmount = $this->proposalMonthlyAmount($proposal, $termMonths);

        return sprintf(
            <<<'HTML'
<section class="proposal-signature-certificate">
<h1>Signed proposal certificate</h1>
<p>This certificate records the client acceptance, payment authority, and identity checks attached to the signed proposal.</p>
<h2>Proposal</h2>
<dl>
<dt>Client</dt><dd>%s</dd>
<dt>Proposal</dt><dd>Proposal v%s</dd>
<dt>Total proposal</dt><dd>NZD %s</dd>
<dt>Term</dt><dd>%s months</dd>
<dt>Monthly collection</dt><dd>NZD %s per month</dd>
<dt>GST</dt><dd>Amounts are GST exclusive. GST at 15%% is added to each payment collected.</dd>
<dt>Collection date</dt><dd>%s of each month</dd>
</dl>
<h2>Signature</h2>
<dl>
<dt>Signed at</dt><dd>%s</dd>
<dt>Signed by</dt><dd>%s &lt;%s&gt;</dd>
<dt>Typed signature</dt><dd>%s</dd>
<dt>User ID</dt><dd>%s</dd>
<dt>IP address</dt><dd>%s</dd>
<dt>User agent</dt><dd>%s</dd>
</dl>
<h2>Identity verification</h2>
<dl>
<dt>Password</dt><dd>Verified at %s</dd>
<dt>MFA</dt><dd>%s</dd>
</dl>
<h2>Payment authority</h2>
<p>Authorised using password%s before signature. The payment method is tokenised by the selected gateway; raw card details are not stored by Future Shift Advisory.</p>
</section>
HTML,
            $this->escape($proposal->client?->legal_name ?? 'Client'),
            $proposal->version,
            number_format($proposal->feeCalculation?->suggested_mid ?? 0, 0),
            $termMonths,
            number_format($monthlyAmount, 0),
            $this->ordinal($collectionDay),
            $this->escape($signedAt->format('j M Y, g:i A T')),
            $this->escape($actor->name),
            $this->escape($actor->email),
            $this->escape($typedName),
            $this->escape((string) $actor->getKey()),
            $this->escape((string) ($payload['ip'] ?? '')),
            $this->escape((string) ($payload['user_agent'] ?? '')),
            $this->escape($this->formatEvidenceDate($identity['password_verified_at'] ?? null)),
            $this->escape($this->mfaEvidenceLine($identity)),
            (bool) ($identity['mfa_required'] ?? false) ? ' and MFA' : '',
        );
    }

    private function attachSignatureEvidence(string $html, string $banner, string $certificate, string $style): string
    {
        if (str_contains($html, '</head>')) {
            $html = str_replace('</head>', "<style>\n{$style}\n</style>\n</head>", $html);
        } else {
            $html = "<style>\n{$style}\n</style>\n".$html;
        }

        if (preg_match('/<body\b[^>]*>/i', $html) === 1) {
            $html = preg_replace('/(<body\b[^>]*>)/i', '$1'.$banner, $html, 1) ?? $html;
        } else {
            $html = $banner.$html;
        }

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $certificate."\n</body>", $html);
        }

        return $html.$certificate;
    }

    /**
     * @return array<string, mixed>
     */
    private function stepPayload(Proposal $proposal, string $step): array
    {
        $proposal->loadMissing('signoffSteps');
        $payload = $proposal->signoffSteps->firstWhere('step', $step)?->payload;

        return is_array($payload) ? $payload : [];
    }

    private function validCollectionDay(mixed $value): int
    {
        $day = is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value
            : 1;

        return in_array($day, [1, 15], true) ? $day : 1;
    }

    private function proposalTermMonths(Proposal $proposal): int
    {
        $months = data_get($proposal->scope, 'term_months')
            ?? data_get($proposal->acceptance_terms, 'term_months')
            ?? data_get($proposal->feeCalculation?->justification, 'retainer.months')
            ?? data_get($proposal->feeCalculation?->justification, 'retainer_months');

        return max(1, (int) (is_numeric($months) ? $months : 6));
    }

    private function proposalMonthlyAmount(Proposal $proposal, int $termMonths): float
    {
        $monthly = data_get($proposal->feeCalculation?->justification, 'retainer.monthly_fee')
            ?? data_get($proposal->feeCalculation?->justification, 'monthly_retainer_fee')
            ?? data_get($proposal->pv_summary, 'monthly_retainer_fee');

        if (is_numeric($monthly) && (float) $monthly > 0) {
            return round((float) $monthly, 2);
        }

        $total = $proposal->feeCalculation?->suggested_mid ?? data_get($proposal->pv_summary, 'fee_suggested_mid', 0);

        return round(((float) $total) / max(1, $termMonths), 2);
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function mfaEvidenceLine(array $identity): string
    {
        if (! (bool) ($identity['mfa_required'] ?? false)) {
            return 'Not required in this environment';
        }

        $method = is_scalar($identity['mfa_method'] ?? null) ? (string) $identity['mfa_method'] : 'Authenticator app';

        return 'Verified at '.$this->formatEvidenceDate($identity['mfa_verified_at'] ?? null).' using '.$method;
    }

    private function formatEvidenceDate(mixed $value): string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return 'Recorded';
        }

        try {
            return Carbon::parse((string) $value)->format('j M Y, g:i A T');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function ordinal(int $day): string
    {
        return match ($day) {
            1 => '1st',
            15 => '15th',
            default => (string) $day,
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
