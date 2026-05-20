<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Support\Public\EngagementTypeCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedInterests = array_map(
            fn (array $opt) => $opt['value'],
            EngagementTypeCatalog::selectOptions(),
        );

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:200'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:160'],
            'engagement_interest' => ['nullable', 'string', 'in:'.implode(',', $allowedInterests)],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            // Honeypot — real users do not see or fill this.
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->filled('website')) {
                // Pretend it succeeded; do not give bots a useful signal.
                throw ValidationException::withMessages([]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.min' => 'Tell us a little more so we can respond usefully.',
            'email.email' => 'That email address does not look right.',
        ];
    }
}
