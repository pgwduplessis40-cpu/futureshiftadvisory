<?php

declare(strict_types=1);

namespace App\Http\Requests\Advisor\Entrepreneurs;

use App\Models\EntrepreneurProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class RequestIdeaChangesRequest extends FormRequest
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
            'change_request_note' => ['required', 'string', 'min:10', 'max:4000'],
        ];
    }
}
