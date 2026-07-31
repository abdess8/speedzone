<?php

namespace Tests\Feature;

use App\Support\PermissionCatalog;
use Tests\TestCase;

/**
 * Ties the frontend navigation definition back to the server permission catalog.
 *
 * The sidebar and the mobile bottom bar decide what to show by testing
 * permission *names* held as strings in `resources/js/navigation/menuItems.js`.
 * A typo there fails silently and in the worst possible direction: the entry
 * simply never appears, for anybody, and nothing in the test suite notices
 * because the route it points at is still perfectly reachable by URL.
 *
 * Reading the JS from PHP is deliberate. Duplicating the navigation tree into a
 * PHP catalog would create a second source of truth that drifts; this asserts
 * against the one the browser actually renders.
 */
class NavigationPermissionsTest extends TestCase
{
    private const MENU_FILE = 'resources/js/navigation/menuItems.js';

    public function test_every_navigation_permission_exists_in_the_catalog(): void
    {
        $known = array_column(PermissionCatalog::all(), 'name');
        $referenced = $this->referencedPermissions();

        $this->assertNotEmpty($referenced, 'No permissions parsed from '.self::MENU_FILE);

        $unknown = array_values(array_diff($referenced, $known));

        $this->assertSame([], $unknown, sprintf(
            'Navigation entries reference permissions that do not exist: %s. '
            .'Such an entry is hidden from every role, silently.',
            implode(', ', $unknown)
        ));
    }

    /**
     * Permission names appearing in `permissions:` lists and in the shared
     * `const X_READ = [...]` groups the menu builds them from.
     *
     * @return array<int, string>
     */
    private function referencedPermissions(): array
    {
        $source = file_get_contents(base_path(self::MENU_FILE));

        $this->assertNotFalse($source, self::MENU_FILE.' is not readable');

        // A permission name is dotted and lowercase — `orders.read.assigned` —
        // which is specific enough to pick out of the file without also matching
        // route names, icon classes or translation keys.
        preg_match_all("/'([a-z_]+(?:\.[a-z_]+)+)'/", $source, $matches);

        $candidates = array_unique($matches[1]);

        // Menu entries also carry Ziggy route names ("users.index") and i18n keys,
        // which share the dotted shape. Only the ones the catalog *could* own —
        // i.e. whose resource prefix it knows — are asserted on.
        $resources = array_unique(array_column(PermissionCatalog::all(), 'resource'));

        return array_values(array_filter(
            $candidates,
            static function (string $candidate) use ($resources): bool {
                $prefix = strstr($candidate, '.', true);

                return in_array($prefix, $resources, true);
            }
        ));
    }
}
