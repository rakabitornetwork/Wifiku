<?php

namespace App\Support;

/**
 * Daftar permission aplikasi dan pembagiannya per role.
 * Owner memiliki seluruh akses (juga lewat Gate::before di AppServiceProvider).
 */
class Permissions
{
    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_MANAGE = 'customers.manage';

    public const PACKAGES_MANAGE = 'packages.manage';

    public const ROUTERS_MANAGE = 'routers.manage';

    public const ASSETS_MANAGE = 'assets.manage';

    public const VOUCHERS_MANAGE = 'vouchers.manage';

    public const INVOICES_VIEW = 'invoices.view';

    public const PAYMENTS_COLLECT = 'payments.collect';

    public const TOPOLOGY_VIEW = 'topology.view';

    public const TOPOLOGY_MANAGE = 'topology.manage';

    public const OUTAGES_MANAGE = 'outages.manage';

    public const EXPENSES_MANAGE = 'expenses.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const USERS_MANAGE = 'users.manage';

    public const AUDIT_VIEW = 'audit.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    /**
     * Permission yang hanya dimiliki Owner.
     *
     * @var list<string>
     */
    public const OWNER_ONLY = [self::USERS_MANAGE, self::AUDIT_VIEW, self::SETTINGS_MANAGE];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $constants = (new \ReflectionClass(self::class))->getConstants();

        return array_values(array_filter($constants, fn (mixed $value, string $name): bool => $name !== 'OWNER_ONLY', ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Permission per role. Owner mendapat semuanya.
     *
     * @return array<string, list<string>>
     */
    public static function byRole(): array
    {
        return [
            'Owner' => self::all(),
            'Admin' => array_values(array_diff(self::all(), self::OWNER_ONLY)),
            'Teknisi' => [self::CUSTOMERS_VIEW, self::ASSETS_MANAGE, self::TOPOLOGY_VIEW, self::OUTAGES_MANAGE],
        ];
    }
}
