<?php

declare(strict_types=1);

namespace App\Services\Integration\Ird;

final class IrdGatewayPolicy
{
    public const STATUS_DEFERRED = 'declined_current_gateway_pending_data_consumer';

    public const SOURCE_BADGE = 'client_supplied_not_ird_verified';

    public const SOURCE_REFERENCE = 'ird:gateway:regulatory-deferred';

    public static function label(): string
    {
        return 'Deferred pending IRD Data Consumer category';
    }

    public static function note(): string
    {
        return 'IRD declined the current Gateway Services application because FSA needs IRD data for advisory verification rather than helping the client meet tax obligations. Reassess when the proposed Data Consumer intermediary category becomes available, currently anticipated from 2027.';
    }

    public static function finding(): string
    {
        return 'IRD number and GST status cannot be independently verified through IRD Gateway under the current permitted-disclosure framework.';
    }

    public static function action(): string
    {
        return 'Treat any IRD number or GST position as client supplied, request documentary support where needed, avoid the phrase IRD verified, and reapply for IRD Data Consumer access when the category opens.';
    }

    /**
     * @return array<string, mixed>
     */
    public static function gstStatusPayload(string $nzbn): array
    {
        return [
            'nzbn' => $nzbn,
            'ird_number' => null,
            'gst_registered' => null,
            'gst_effective_from' => null,
            'payroll_registered' => null,
            'status' => 'Not verified with IRD',
            'verification_status' => self::STATUS_DEFERRED,
            'verification_label' => 'Client supplied - not verified with IRD',
            'finding' => self::finding(),
            'required_action' => self::action(),
            'regulatory_note' => self::note(),
            'source_badge' => self::SOURCE_BADGE,
            'degraded' => false,
        ];
    }
}
