<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CS-03: user-facing payment initiation. Any authenticated caller
 * that owns the target application (org scope enforced by the
 * controller) may initiate. Callback URL is optional per-request —
 * providers that accept a per-transaction override can consume it,
 * others ignore it.
 */
class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'callback_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
