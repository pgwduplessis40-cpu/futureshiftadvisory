<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coach;

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
        abort_unless($user instanceof User && $user->can(Permission::COACH_PORTAL->value), 403);

        $member = PanelMember::query()
            ->where('user_id', $user->getKey())
            ->where('panel_type', PanelMember::TYPE_COACH)
            ->first();

        abort_unless($member instanceof PanelMember, 403);
        abort_unless(
            (string) $referral->panel_member_id === (string) $member->getKey()
                && $referral->panel_type === PanelMember::TYPE_COACH,
            403,
        );

        $validated = $request->validate([
            'stage' => ['required', 'string', Rule::in([
                Referral::STAGE_COACH_ACCEPTED,
                Referral::STAGE_COACHING_UNDERWAY,
                Referral::STAGE_COACH_CONCLUDED,
                Referral::STAGE_COACH_DECLINED,
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
            'message' => __('Coach referral status updated.'),
        ]);

        return back();
    }
}
