<?php

declare(strict_types=1);

namespace App\Http\Requests\Advisor\Entrepreneurs;

use App\Models\EntrepreneurProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class AssessmentFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('entrepreneurProfile');

        return $profile instanceof EntrepreneurProfile
            && Gate::allows('assess', $profile);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'feedback' => ['required', 'string', 'min:10', 'max:4000'],
            'proposed_reply' => ['required', 'string', 'min:10', 'max:4000'],
            'send_to_founder' => ['required', 'boolean'],
        ];
    }
}
