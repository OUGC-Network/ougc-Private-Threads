<?php

/***************************************************************************
 *
 *    ougc Private Thread plugin (/inc/plugins/ougcPrivateThreads.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2020 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Allow users to mark individual threads as private to be visible for specific users only.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

use function ougc\PrivateThreads\Admin\pluginInformation;
use function ougc\PrivateThreads\Admin\pluginActivate;
use function ougc\PrivateThreads\Admin\pluginInstall;
use function ougc\PrivateThreads\Admin\pluginIsInstalled;
use function ougc\PrivateThreads\Admin\pluginUninstall;
use function ougc\PrivateThreads\Core\addHooks;
use function ougc\PrivateThreads\MyAlerts\initLocations;
use function ougc\PrivateThreads\MyAlerts\initMyalerts;
use function ougc\PrivateThreads\MyAlerts\MyAlertsIsIntegrable;

use const ougc\PrivateThreads\ROOT;

defined('IN_MYBB') || die('This file cannot be accessed directly.');

// You can uncomment the lines below to avoid storing some settings in the DB
define('ougc\PrivateThreads\Core\SETTINGS', [
    //'key' => '',
]);

define('ougc\PrivateThreads\Core\DEBUG', false);

define('ougc\PrivateThreads\ROOT', constant('MYBB_ROOT') . 'inc/plugins/ougc/PrivateThreads');

require_once ROOT . '/core.php';

defined('PLUGINLIBRARY') || define('PLUGINLIBRARY', constant('MYBB_ROOT') . 'inc/plugins/pluginlibrary.php');

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';

    require_once ROOT . '/hooks/admin.php';

    addHooks('ougc\PrivateThreads\Hooks\Admin');
} else {
    require_once ROOT . '/hooks/forum.php';

    addHooks('ougc\PrivateThreads\Hooks\Forum');
}

require ROOT . '/myalerts.php';

if (MyAlertsIsIntegrable()) {
    initMyalerts();

    initLocations();
}

function ougcPrivateThreads_info(): array
{
    return pluginInformation();
}

function ougcPrivateThreads_activate(): void
{
    pluginActivate();
}

function ougcPrivateThreads_install(): void
{
    pluginInstall();
}

function ougcPrivateThreads_is_installed(): bool
{
    return pluginIsInstalled();
}

function ougcPrivateThreads_uninstall(): void
{
    pluginUninstall();
}