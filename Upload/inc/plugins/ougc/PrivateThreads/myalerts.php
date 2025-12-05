<?php

/***************************************************************************
 *
 *    ougc Private Thread plugin (/inc/plugins/ougc/PrivateThreads/myalerts.php)
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

namespace ougc\PrivateThreads\MyAlerts;

use MybbStuff_Core_ClassLoader;
use MybbStuff_MyAlerts_AlertFormatterManager;
use MybbStuff_MyAlerts_AlertTypeManager;
use MybbStuff_MyAlerts_Entity_AlertType;

use const ougc\PrivateThreads\ROOT;

function getAvailableLocations(): array
{
    $directoryContents = ROOT . '/myalerts/';

    return array_map(
        'basename',
        glob($directoryContents . '*', GLOB_ONLYDIR)
    );
}

function getInstalledLocations(): array
{
    global $cache;

    return $cache->read('ougcPrivateThreadsAlerts')['MyAlertLocationsInstalled'] ?? [];
}

function isLocationAlertTypePresent(string $locationName): bool
{
    if (MyAlertsIsIntegrable()) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        return $alertTypeManager->getByCode('ougcPrivateThreads' . $locationName) !== null;
    }

    return false;
}

function installLocation(string $locationName): void
{
    global $cache;

    $cacheEntry = $cache->read('ougcPrivateThreadsAlerts');

    if (!in_array($locationName, $cacheEntry['MyAlertLocationsInstalled'])) {
        $cacheEntry['MyAlertLocationsInstalled'][] = $locationName;

        $cache->update('ougcPrivateThreadsAlerts', $cacheEntry);
    }

    if (!isLocationAlertTypePresent($locationName)) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();

        $alertType->setCode('ougcPrivateThreads' . $locationName);

        $alertTypeManager->add($alertType);
    }
}

function uninstallLocation(string $locationName): void
{
    global $cache;

    // remove MyAlerts type
    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

    $alertTypeManager->deleteByCode('ougcPrivateThreads' . $locationName);

    // remove datacache value
    $cacheEntry = $cache->read('ougcPrivateThreadsAlerts');
    $key = array_search($locationName, $cacheEntry['MyAlertLocationsInstalled']);

    if ($key !== false) {
        unset($cacheEntry['MyAlertLocationsInstalled'][$key]);
        $cache->update('ougcPrivateThreadsAlerts', $cacheEntry);
    }
}

function initMyalerts(): void
{
    defined('MYBBSTUFF_CORE_PATH') || define(
        'MYBBSTUFF_CORE_PATH',
        constant('MYBB_ROOT') . 'inc/plugins/MybbStuff/Core/'
    );

    defined('MYALERTS_PLUGIN_PATH') || define(
        'MYALERTS_PLUGIN_PATH',
        constant('MYBB_ROOT') . 'inc/plugins/MybbStuff/MyAlerts'
    );

    require_once MYBBSTUFF_CORE_PATH . 'ClassLoader.php';

    $classLoader = new MybbStuff_Core_ClassLoader();

    $classLoader->registerNamespace('MybbStuff_MyAlerts', [MYALERTS_PLUGIN_PATH . '/src']);

    $classLoader->register();
}

function initLocations(): void
{
    foreach (getInstalledLocations() as $locationName) {
        require_once ROOT . '/myalerts/' . $locationName . '/init.php';
    }
}

function registerMyAlertsFormatters(): void
{
    global $mybb, $lang, $formatterManager;

    $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

    $formatterManager || $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);

    foreach (getInstalledLocations() as $locationName) {
        $class = 'MybbStuff_MyAlerts_Formatter_ougcPrivateThreads' . ucfirst($locationName) . 'Formatter';

        $formatter = new $class($mybb, $lang, 'ougcPrivateThreads' . $locationName);

        $formatterManager->registerFormatter($formatter);
    }
}

function MyAlertsIsIntegrable(): bool
{
    global $cache;

    static $status;

    if (!$status) {
        $status = false;

        $plugins = $cache->read('plugins');

        if (!empty($plugins['active']) && in_array('myalerts', $plugins['active'])) {
            if ($cacheData = $cache->read('euantor_plugins')) {
                if (isset($cacheData['myalerts']['version'])) {
                    $version = explode('.', $cacheData['myalerts']['version']);

                    if ($version[0] == '2' && $version[1] == '0') {
                        $status = true;
                    }
                }
            }
        }
    }

    return $status;
}