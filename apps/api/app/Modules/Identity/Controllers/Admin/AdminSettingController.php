<?php

namespace App\Modules\Identity\Controllers\Admin;

use App\Modules\Identity\Models\PlatformSetting;
use App\Modules\Identity\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AdminSettingController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && ($user->can($permission) || $user->isSuperAdmin()),
            403,
            'You do not have permission to perform this settings action.'
        );
    }

    /**
     * Default platform settings seeded automatically when first accessed.
     */
    private function defaultSettings(): array
    {
        return [
            [
                'group'       => 'general',
                'key'         => 'store.name',
                'value'       => ['val' => 'Atelier Dhaka'],
                'type'        => 'string',
                'label'       => 'Store Name',
                'description' => 'Public brand name displayed on storefront headers, emails, and invoices.',
                'is_public'   => true,
            ],
            [
                'group'       => 'general',
                'key'         => 'store.support_email',
                'value'       => ['val' => 'support@clothing-commerce.test'],
                'type'        => 'string',
                'label'       => 'Customer Support Email',
                'description' => 'Primary support contact address for order inquiries.',
                'is_public'   => true,
            ],
            [
                'group'       => 'general',
                'key'         => 'store.support_phone',
                'value'       => ['val' => '+8801700000000'],
                'type'        => 'string',
                'label'       => 'Customer Support Phone',
                'description' => 'Bangladesh helpline number displayed in footer and order receipts.',
                'is_public'   => true,
            ],
            [
                'group'       => 'commerce',
                'key'         => 'commerce.currency',
                'value'       => ['val' => 'BDT'],
                'type'        => 'string',
                'label'       => 'Default Currency',
                'description' => 'ISO 4217 currency code. All monetary values are stored in integer paisa.',
                'is_public'   => true,
            ],
            [
                'group'       => 'commerce',
                'key'         => 'commerce.allow_guest_checkout',
                'value'       => ['val' => true],
                'type'        => 'boolean',
                'label'       => 'Allow Guest Checkout',
                'description' => 'Permit customers to place orders without creating an account.',
                'is_public'   => true,
            ],
            [
                'group'       => 'commerce',
                'key'         => 'commerce.low_stock_threshold',
                'value'       => ['val' => 5],
                'type'        => 'integer',
                'label'       => 'Default Low-Stock Alert Threshold',
                'description' => 'Default unit threshold below which variants trigger low-stock alerts.',
                'is_public'   => false,
            ],
            [
                'group'       => 'shipping',
                'key'         => 'shipping.inside_dhaka_fee_paisa',
                'value'       => ['val' => 7000],
                'type'        => 'integer',
                'label'       => 'Inside Dhaka Standard Fee (Paisa)',
                'description' => 'Standard delivery charge inside Dhaka Metro in integer paisa (7000 = BDT 70).',
                'is_public'   => true,
            ],
            [
                'group'       => 'shipping',
                'key'         => 'shipping.outside_dhaka_fee_paisa',
                'value'       => ['val' => 13000],
                'type'        => 'integer',
                'label'       => 'Outside Dhaka Standard Fee (Paisa)',
                'description' => 'Standard nationwide delivery charge in integer paisa (13000 = BDT 130).',
                'is_public'   => true,
            ],
            [
                'group'       => 'shipping',
                'key'         => 'shipping.default_courier',
                'value'       => ['val' => 'pathao'],
                'type'        => 'string',
                'label'       => 'Default Courier Provider',
                'description' => 'Default courier adapter used when dispatching consignments (pathao, steadfast, redx).',
                'is_public'   => false,
            ],
            [
                'group'       => 'payments',
                'key'         => 'payments.cod_enabled',
                'value'       => ['val' => true],
                'type'        => 'boolean',
                'label'       => 'Cash on Delivery (COD) Enabled',
                'description' => 'Enable Cash on Delivery for Bangladesh orders.',
                'is_public'   => true,
            ],
            [
                'group'       => 'security',
                'key'         => 'security.maintenance_mode',
                'value'       => ['val' => false],
                'type'        => 'boolean',
                'label'       => 'Storefront Maintenance Mode',
                'description' => 'Temporarily pause public checkout while keeping admin backoffice active.',
                'is_public'   => true,
            ],
        ];
    }

    private function ensureDefaultsSeeded(): void
    {
        if (PlatformSetting::query()->count() > 0) {
            return;
        }

        foreach ($this->defaultSettings() as $item) {
            PlatformSetting::firstOrCreate(
                ['key' => $item['key']],
                $item
            );
        }
    }

    /**
     * GET /api/admin/settings
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'settings.view');

        $this->ensureDefaultsSeeded();

        $query = PlatformSetting::query()->orderBy('group')->orderBy('key');
        if ($request->filled('group')) {
            $query->where('group', (string) $request->query('group'));
        }

        $settings = $query->get()->map(fn (PlatformSetting $s) => $this->formatSetting($s));

        return response()->json([
            'data' => $settings->values()->all(),
        ]);
    }

    /**
     * PUT /api/admin/settings
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        $this->ensureDefaultsSeeded();

        $validated = $request->validate([
            'settings'               => ['required', 'array', 'min:1'],
            'settings.*.key'         => ['required', 'string', 'max:120'],
            'settings.*.value'       => ['present'],
            'settings.*.group'       => ['nullable', 'string', 'max:60'],
            'settings.*.type'        => ['nullable', 'string', 'in:string,integer,boolean,json'],
            'settings.*.label'       => ['nullable', 'string', 'max:191'],
            'settings.*.description' => ['nullable', 'string', 'max:500'],
            'settings.*.is_public'   => ['nullable', 'boolean'],
        ]);

        $actor = $request->user('sanctum') ?? $request->user();
        $changes = [];

        DB::transaction(function () use ($validated, $actor, &$changes) {
            foreach ($validated['settings'] as $entry) {
                $existing = PlatformSetting::query()->where('key', $entry['key'])->first();
                $oldVal = $existing ? ($existing->value['val'] ?? $existing->value) : null;
                $newVal = $entry['value'];

                $setting = PlatformSetting::updateOrCreate(
                    ['key' => $entry['key']],
                    [
                        'group'       => $entry['group'] ?? $existing?->group ?? 'general',
                        'value'       => ['val' => $newVal],
                        'type'        => $entry['type'] ?? $existing?->type ?? 'string',
                        'label'       => $entry['label'] ?? $existing?->label ?? $entry['key'],
                        'description' => array_key_exists('description', $entry) ? $entry['description'] : $existing?->description,
                        'is_public'   => array_key_exists('is_public', $entry) ? (bool) $entry['is_public'] : (bool) ($existing?->is_public ?? false),
                        'updated_by'  => $actor?->id,
                    ]
                );

                $changes[$setting->key] = [
                    'old' => $oldVal,
                    'new' => $newVal,
                ];
            }
        });

        $this->auditService->log('settings.updated', $actor, null, null, $changes);

        $all = PlatformSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->map(fn (PlatformSetting $s) => $this->formatSetting($s))
            ->values()
            ->all();

        return response()->json([
            'message' => 'Platform settings updated successfully.',
            'data'    => $all,
        ]);
    }

    private function formatSetting(PlatformSetting $setting): array
    {
        $rawVal = is_array($setting->value) && array_key_exists('val', $setting->value)
            ? $setting->value['val']
            : $setting->value;

        return [
            'id'          => $setting->id,
            'group'       => $setting->group,
            'key'         => $setting->key,
            'value'       => $rawVal,
            'type'        => $setting->type,
            'label'       => $setting->label,
            'description' => $setting->description,
            'is_public'   => (bool) $setting->is_public,
            'updated_by'  => $setting->updated_by,
            'updated_at'  => $setting->updated_at?->toIso8601String(),
        ];
    }
}

