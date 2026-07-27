<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\JeaProjects\Engine\Disciplines;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaServices\Http\Requests\SubmitOfficeRegistrationRequest;
use Modules\JeaServices\Models\OfficeRegistrationRequest;

/**
 * OfficeRegistrationController — public signup + JEA admin review.
 *
 * See docs/architecture/office-registration-flow.md.
 *
 * Public endpoints:
 *   POST /api/v1/office-registrations           — anonymous submit
 *
 * Admin endpoints (auth + role check):
 *   GET  /api/v1/admin/office-registrations
 *   GET  /api/v1/admin/office-registrations/{id}
 *   POST /api/v1/admin/office-registrations/{id}/approve
 *   POST /api/v1/admin/office-registrations/{id}/reject
 *
 * Approval creates the Organization + admin User + Engineer records
 * that the rest of the platform consumes. All three creations happen
 * in a single DB transaction so a partial provisioning is impossible.
 */
class OfficeRegistrationController extends Controller
{
    // ── Public submit ─────────────────────────────────────────────

    public function submit(SubmitOfficeRegistrationRequest $request): JsonResponse
    {
        $ref = OfficeRegistrationRequest::generateReference();

        $registration = OfficeRegistrationRequest::create([
            'reference_number'   => $ref,
            'office_name_ar'     => $request->input('office_name_ar'),
            'office_name_en'     => $request->input('office_name_en'),
            'office_license_no'  => $request->input('office_license_no'),
            'office_city'        => $request->input('office_city'),
            'office_address_ar'  => $request->input('office_address_ar'),
            'office_phone'       => $request->input('office_phone'),
            'office_email'       => $request->input('office_email'),
            'engineers'          => $request->input('engineers', []),
            'status'             => OfficeRegistrationRequest::STATUS_PENDING,
            'submitter_ip'       => $request->ip(),
            'submitter_user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return response()->json([
            'message'          => 'تم استلام طلب تسجيل المكتب. ستراجعه النقابة قريباً.',
            'reference_number' => $registration->reference_number,
            'status'           => $registration->status,
            'id'               => $registration->id,
        ], 201);
    }

    // ── Admin review ──────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $status = $request->query('status', OfficeRegistrationRequest::STATUS_PENDING);
        $rows = OfficeRegistrationRequest::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json(['registrations' => $rows]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $reg = OfficeRegistrationRequest::with(['reviewer:id,name,email,role', 'approvedOrganization'])
            ->findOrFail($id);

        return response()->json(['registration' => $reg]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $reg = OfficeRegistrationRequest::findOrFail($id);
        if (! $reg->isPending()) {
            return response()->json([
                'message' => 'لا يمكن اعتماد طلب في حالة "' . $reg->status . '". يقبل الاعتماد الحالات المعلَّقة فقط.',
            ], 422);
        }

        $notes = (string) $request->input('review_notes', '');

        // All provisioning in one transaction — partial state is unacceptable.
        $organization = DB::transaction(function () use ($reg, $request, $notes) {
            $org = Organization::create([
                'name_ar'   => $reg->office_name_ar,
                'name_en'   => $reg->office_name_en,
                'slug'      => $this->uniqueSlugFor($reg->office_name_en, $reg->office_license_no),
                'is_active' => true,
            ]);

            // The admin User of the new office. Uses the office email + a
            // random password — real prod would send an email reset link;
            // here we generate a strong random one and leave it to admin
            // to communicate via a password-reset flow.
            $officeAdmin = User::create([
                'organization_id'     => $org->id,
                'name'                => $reg->office_name_ar,
                'email'               => $reg->office_email,
                'password'            => Hash::make(Str::random(32)),
                'role'                => 'applicant',
                'phone'               => $reg->office_phone,
                'is_active'           => true,
                'password_changed_at' => now(),
            ]);

            // Engineers are attached to the office admin user (existing model
            // convention — Engineer::office_user_id points to the applicant
            // User of the owning office).
            foreach ($reg->engineers as $eng) {
                Engineer::create([
                    'organization_id'   => $org->id,
                    'office_user_id'    => $officeAdmin->id,
                    'name_ar'           => $eng['name_ar']           ?? '',
                    'name_en'           => $eng['name_en']           ?? '',
                    'membership_number' => $eng['membership_number'] ?? '',
                    'specialization'    => Disciplines::normalize($eng['specialization'] ?? ''),
                    'phone'             => $eng['phone']             ?? null,
                    'email'             => $eng['email']             ?? null,
                    'is_active'         => true,
                ]);
            }

            $reg->update([
                'status'                   => OfficeRegistrationRequest::STATUS_APPROVED,
                'review_notes'             => $notes ?: null,
                'reviewed_by_user_id'      => $request->user()->id,
                'reviewed_at'              => now(),
                'approved_organization_id' => $org->id,
            ]);

            return $org;
        });

        return response()->json([
            'message'         => 'تم اعتماد المكتب وإنشاء الحساب والكادر الهندسي.',
            'registration_id' => $reg->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $reg = OfficeRegistrationRequest::findOrFail($id);
        if (! $reg->isPending()) {
            return response()->json([
                'message' => 'لا يمكن رفض طلب في حالة "' . $reg->status . '". يقبل الرفض الحالات المعلَّقة فقط.',
            ], 422);
        }

        $notes = trim((string) $request->input('review_notes', ''));
        if ($notes === '') {
            return response()->json([
                'message' => 'يجب توضيح سبب الرفض في review_notes.',
                'errors'  => ['review_notes' => 'سبب الرفض مطلوب.'],
            ], 422);
        }

        $reg->update([
            'status'              => OfficeRegistrationRequest::STATUS_REJECTED,
            'review_notes'        => $notes,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at'         => now(),
        ]);

        return response()->json([
            'message'         => 'تم رفض طلب تسجيل المكتب.',
            'registration_id' => $reg->id,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['admin', 'superuser'], true)) {
            abort(403, 'الاعتماد أو الرفض متاح لمدير النقابة فقط.');
        }
    }

    /**
     * Generate a URL-safe slug from the English name, falling back to
     * the license number for uniqueness. The organizations table has a
     * unique slug column.
     */
    private function uniqueSlugFor(string $nameEn, string $licenseNo): string
    {
        $base = Str::slug($nameEn) ?: strtolower($licenseNo);
        $slug = $base;
        $suffix = 1;
        while (Organization::where('slug', $slug)->exists()) {
            $slug = "{$base}-" . (++$suffix);
        }
        return $slug;
    }
}
