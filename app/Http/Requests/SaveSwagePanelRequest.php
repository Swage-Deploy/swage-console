<?php

namespace App\Http\Requests;

use App\Models\SwagePanel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSwagePanelRequest extends FormRequest
{
    /**
     * The console has no sign-in, so anyone who can open it may manage panels.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'url' => rtrim(trim((string) $this->input('url')), '/'),
            'enabled' => $this->boolean('enabled'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A saved token is never shown again, so leaving it blank keeps it — unless the URL
     * changes, which needs the token again so nobody can redirect a saved key to another host.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $panel = $this->route('panel');

        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'token' => [
                Rule::requiredIf(! $panel instanceof SwagePanel || $panel->url !== $this->input('url')),
                'nullable',
                'string',
                'max:500',
            ],
            'enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Enter the API token. It is needed for a new panel, or when its URL changes.',
        ];
    }

    /**
     * The validated attributes to save, leaving the stored token alone when none was entered.
     *
     * @return array<string, mixed>
     */
    public function panelAttributes(): array
    {
        return array_filter(
            $this->safe()->only(['name', 'url', 'token', 'enabled']),
            fn (mixed $value, string $key): bool => $key !== 'token' || filled($value),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
