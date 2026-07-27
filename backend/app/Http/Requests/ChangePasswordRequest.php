<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

/** P-3: FormRequest for POST /auth/password/change (auth:sanctum group). */
class ChangePasswordRequest extends FormRequest
{
    /**
     * Superusers who already rotated their bootstrap credentials can't
     * rotate again via the API — only `php artisan user:credentials`.
     * Checked in authorize() (not the controller body) so it still runs
     * BEFORE validation, matching the original inline-validate() order:
     * a blocked superuser gets the 403 CLI-redirect message even if
     * their request body is also malformed, never a 422 first.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return ! ($user->isSuperuser() && ! $user->must_change_password);
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        if ($user && $user->isSuperuser() && ! $user->must_change_password) {
            throw new HttpResponseException(response()->json([
                'message' => 'يمكن تغيير بيانات المستخدم الأعلى فقط من خلال سطر الأوامر: '
                           .'php artisan user:credentials '.$user->email,
            ], 403));
        }

        parent::failedAuthorization();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];

        // On first login the superuser may pick their own login email.
        $user = $this->user();
        if ($user->isSuperuser() && $user->must_change_password) {
            $rules['email'] = ['sometimes', 'email', 'unique:users,email,'.$user->id];
        }

        return $rules;
    }
}
