<?php

declare(strict_types=1);

namespace App\Http\Requests\Advisor\Entrepreneurs;

use App\Models\EntrepreneurProfile;
use App\Models\ServiceRatePackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('entrepreneurProfile');

        return $profile instanceof EntrepreneurProfile
            && Gate::allows('manageInvite', $profile);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $profile = $this->route('entrepreneurProfile');
        $profileId = $profile instanceof EntrepreneurProfile ? $profile->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('entrepreneur_profiles', 'email')->ignore($profileId),
                Rule::unique('users', 'email'),
            ],
            'concept_summary' => ['nullable', 'string', 'max:2000'],
            'intended_package_scope' => [
                'required',
                'string',
                Rule::in(ServiceRatePackage::entrepreneurPackageScopes()),
            ],
        ];
    }
}
