<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

/**
 * P-3: FormRequest for PUT /admin/users/{id}.
 *
 * Fetches + tier-checks the target in authorize() (not the controller
 * body) so the ordering matches the original inline-validate() code:
 * "can this actor manage users at all" then "can this actor touch THIS
 * target's current tier" both run BEFORE body validation — a request
 * that fails both authorization and validation still gets the 403
 * tier-crossing message, never a 422 first.
 */
class UpdateUserRequest extends FormRequest
{
    private ?User $target = null;

    private string $authFailureMessage = 'إدارة المستخدمين محصورة بالمستخدم الأعلى والمدير.';

    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor?->canManageUsers()) {
            return false;
        }

        $this->target = User::where('organization_id', $actor->organization_id)
            ->findOrFail($this->route('id'));

        if (! $actor->canManageRole($this->target->role)) {
            $this->authFailureMessage = 'ليس لديك صلاحية لإدارة حسابات بهذا المستوى — العملية محصورة بالمستخدم الأعلى.';

            return false;
        }

        return true;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $this->authFailureMessage,
        ], 403));
    }

    /** The target user fetched + tier-checked during authorize(). */
    public function target(): User
    {
        return $this->target;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$this->route('id')],
            'role' => ['sometimes', 'in:applicant,staff,auditor,admin,superuser'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', Password::min(8)->mixedCase()->numbers()],
        ];
    }
}
