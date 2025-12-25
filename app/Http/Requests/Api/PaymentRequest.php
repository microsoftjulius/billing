<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Adjust based on your auth logic
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:500', 'max:1000000'], // Min 500 UGX, Max 1,000,000 UGX
            'phone' => ['required', 'string', 'regex:/^256[0-9]{9}$/'], // Format: 256XXXXXXXXX
            'email' => ['nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'package' => ['required', 'string', Rule::in([
                'daily_1gb', 'weekly_5gb', 'monthly_20gb',
                'unlimited_daily', 'unlimited_weekly', 'unlimited_monthly',
                'daily', 'weekly', 'monthly'
            ])],
            'validity_hours' => ['nullable', 'integer', 'min:1', 'max:2160'], // Max 90 days
            'description' => ['nullable', 'string', 'max:500'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(['UGX', 'USD', 'KES'])]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Payment amount is required',
            'amount.numeric' => 'Amount must be a number',
            'amount.min' => 'Minimum payment amount is 500 UGX',
            'amount.max' => 'Maximum payment amount is 1,000,000 UGX',
            'phone.required' => 'Phone number is required',
            'phone.regex' => 'Phone number must be in format 256XXXXXXXXX',
            'email.email' => 'Please provide a valid email address',
            'package.required' => 'Package selection is required',
            'package.in' => 'Selected package is not valid',
            'validity_hours.min' => 'Validity must be at least 1 hour',
            'validity_hours.max' => 'Validity cannot exceed 90 days',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'amount' => 'payment amount',
            'phone' => 'phone number',
            'email' => 'email address',
            'name' => 'customer name',
            'package' => 'internet package',
            'validity_hours' => 'validity period',
            'description' => 'payment description',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure phone is in correct format
        $phone = $this->phone;

        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert to 256 format if needed
        if (strlen($phone) === 9 && str_starts_with($phone, '7')) {
            $phone = '256' . $phone;
        } elseif (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '256' . substr($phone, 1);
        }

        $this->merge([
            'phone' => $phone,
            'currency' => strtoupper($this->currency ?? 'UGX'),
            'amount' => (float) $this->amount,
        ]);
    }

    /**
     * Get the validated data with defaults.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Set defaults
        $validated['currency'] = $validated['currency'] ?? 'UGX';
        $validated['description'] = $validated['description'] ?? 'Internet Voucher Payment';
        $validated['name'] = $validated['name'] ?? 'Customer';

        // Set validity hours based on package if not provided
        if (!isset($validated['validity_hours'])) {
            $validated['validity_hours'] = $this->getValidityHours($validated['package'] ?? 'daily');
        }

        return $validated;
    }

    /**
     * Get validity hours for a package.
     */
    private function getValidityHours(string $package): int
    {
        return match($package) {
            'daily_1gb', 'unlimited_daily', 'daily' => 24,
            'weekly_5gb', 'unlimited_weekly', 'weekly' => 168, // 7 days
            'monthly_20gb', 'unlimited_monthly', 'monthly' => 720, // 30 days
            default => 24
        };
    }
}
