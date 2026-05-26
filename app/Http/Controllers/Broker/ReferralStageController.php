<?php

declare(strict_types=1);

namespace App\Http\Controllers\Broker;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PanelMember;
use App\Models\Referral;
use App\Models\User;
use App\Services\Panels\ReferralLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final class ReferralStageController extends Controller
{
    public function __invoke(Request $request, Referral $referral, ReferralLifecycle $lifecycle): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can(Permission::BROKER_PORTAL->value), 403);

        $member = PanelMember::query()
            ->where('user_id', $user->getKey())
            ->where('panel_type', PanelMember::TYPE_BROKER)
            ->first();

        abort_unless($member instanceof PanelMember, 403);
        abort_unless(
            (string) $referral->panel_member_id === (string) $member->getKey()
                && $referral->panel_type === PanelMember::TYPE_BROKER,
            403,
        );

        $validated = $request->validate([
            'stage' => ['required', 'string', Rule::in([
                Referral::STAGE_BROKER_ACKNOWLEDGED,
                Referral::STAGE_BROKER_QUOTE_REQUESTED,
                Referral::STAGE_BROKER_COVER_PLACED,
                Referral::STAGE_BROKER_DECLINED,
                Referral::STAGE_BROKER_NO_RESPONSE,
                Referral::STAGE_WITHDRAWN,
            ])],
        ]);

        try {
            $lifecycle->transition($referral, (string) $validated['stage'], $user);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'stage' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Referral status updated.'),
        ]);

        return back();
    }
}
