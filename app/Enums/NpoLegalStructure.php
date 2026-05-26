<?php

declare(strict_types=1);

namespace App\Enums;

enum NpoLegalStructure: string
{
    case RegisteredCharity = 'registered_charity';
    case IncorporatedSociety = 'incorporated_society';
    case RegisteredCharityAndIncorporatedSociety = 'registered_charity_and_incorporated_society';
    case CharitableTrustBoard = 'charitable_trust_board';
    case CommunityTrustOrFoundation = 'community_trust_or_foundation';
    case SocialEnterpriseRegisteredCharity = 'social_enterprise_registered_charity';
    case SocialEnterpriseNotRegistered = 'social_enterprise_not_registered';
    case UnincorporatedCommunityOrganisation = 'unincorporated_community_organisation';

    public function label(): string
    {
        return match ($this) {
            self::RegisteredCharity => 'Registered Charity',
            self::IncorporatedSociety => 'Incorporated Society',
            self::RegisteredCharityAndIncorporatedSociety => 'Both Registered Charity and Incorporated Society',
            self::CharitableTrustBoard => 'Charitable Trust Board',
            self::CommunityTrustOrFoundation => 'Community Trust or Foundation',
            self::SocialEnterpriseRegisteredCharity => 'Social Enterprise (registered charity)',
            self::SocialEnterpriseNotRegistered => 'Social Enterprise (not registered)',
            self::UnincorporatedCommunityOrganisation => 'Unincorporated Community Organisation',
        };
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $structure): array => [
                'value' => $structure->value,
                'label' => $structure->label(),
            ],
            self::cases(),
        );
    }
}
