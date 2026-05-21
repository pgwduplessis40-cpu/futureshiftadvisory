<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Controller;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);

        $profile = EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->first();

        return Inertia::render('portal/entrepreneur/Dashboard', [
            'profile' => $profile ? [
                'id' => $profile->id,
                'name' => $profile->name,
                'email' => $profile->email,
                'stage' => $profile->stage instanceof EntrepreneurStage
                    ? $profile->stage->value
                    : (string) $profile->stage,
                'stage_label' => $profile->stage instanceof EntrepreneurStage
                    ? $profile->stage->label()
                    : EntrepreneurStage::from((string) $profile->stage)->label(),
                'concept_summary' => $profile->concept_summary,
            ] : null,
        ]);
    }
}
