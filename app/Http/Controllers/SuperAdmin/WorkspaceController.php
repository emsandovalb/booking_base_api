<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Support\BrandingConfig;
use App\Services\SuperAdmin\AuditLogService;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function show(Business $business, Request $request, AuditLogService $auditLogService)
    {
        $business->loadCount([
            'courts as services_count',
            'staff as staff_count',
            'bookings as reservations_count',
            'users as members_count',
            'users as active_members_count' => function ($query) {
                $query->wherePivot('status', 'active');
            },
            'users as pending_members_count' => function ($query) {
                $query->wherePivot('status', 'pending');
            },
            'users as admins_count' => function ($query) {
                $query->wherePivot('status', 'active')->wherePivotIn('role', ['owner', 'admin']);
            },
        ])->load([
            'courts' => function ($query) {
                $query->select('id', 'business_id', 'name', 'rating', 'status', 'images', 'created_at')
                    ->latest();
            },
            'users' => function ($query) {
                $query->select('users.id', 'users.name', 'users.email')
                    ->orderBy('name');
            },
        ]);

        $configPreview = $this->configPreview($business);
        $galleryPhotosCount = $business->courts->sum(function ($court) {
            return is_array($court->images) ? count($court->images) : 0;
        });

        $filters = $this->activityFilters($request);
        $logs = $auditLogService->recentForBusiness($business, $filters, 20);

        return view('super-admin.workspace', [
            'business' => $business,
            'configPreview' => $configPreview,
            'galleryPhotosCount' => $galleryPhotosCount,
            'reviewsCount' => (int) data_get($configPreview, 'identity.review_count', 0),
            'businessInitials' => $this->businessInitials($business->name),
            'enabledFeatures' => $this->enabledFeatures($configPreview),
            'activityLogs' => $logs,
            'activityRows' => $logs->map(fn (AuditLog $log) => $auditLogService->present($log)),
            'activityFilters' => $filters,
            'activityActionOptions' => AuditLog::actionOptions(),
            'activityUsers' => $business->users,
        ]);
    }

    private function configPreview(Business $business): array
    {
        return BrandingConfig::resolveForBusiness($business);
    }

    private function businessInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(function (string $part) {
                return mb_substr($part, 0, 1);
            })
            ->implode('');

        return strtoupper($initials ?: mb_substr($name, 0, 2));
    }

    private function enabledFeatures(array $configPreview): array
    {
        $labels = [
            'show_gallery' => 'Gallery',
            'show_reviews' => 'Reviews',
            'show_staff' => 'Staff visibility',
            'reservation_staff_selection' => 'Staff selection',
            'admin_staff_management' => 'Staff management',
            'show_business_profile' => 'Business profile',
            'show_admin_dashboard' => 'Admin dashboard',
        ];

        $features = data_get($configPreview, 'features', []);
        $enabled = [];

        foreach ($labels as $key => $label) {
            if (data_get($features, $key)) {
                $enabled[] = $label;
            }
        }

        return $enabled;
    }

    private function activityFilters(Request $request): array
    {
        return [
            'date' => $request->string('date')->toString(),
            'action' => $request->string('action')->toString(),
            'user' => $request->string('user')->toString(),
            'search' => $request->string('search')->toString(),
            'business' => $request->string('business')->toString(),
        ];
    }
}
