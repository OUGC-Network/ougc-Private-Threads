<?php

/***************************************************************************
 *
 *    OUGC Private Threads plugin (/inc/plugins/ougcPrivateThreads.php)
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

use function ougc\PrivateThreads\Admin\pluginInfo;
use function ougc\PrivateThreads\Admin\pluginActivate;
use function ougc\PrivateThreads\Admin\pluginDeactivate;
use function ougc\PrivateThreads\Admin\pluginInformation;
use function ougc\PrivateThreads\Admin\pluginInstall;
use function ougc\PrivateThreads\Admin\pluginIsInstalled;
use function ougc\PrivateThreads\Admin\pluginUninstall;
use function ougc\PrivateThreads\Core\addHooks;
use function ougc\PrivateThreads\Core\loadLanguage;

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

function ougcPrivateThreads_activate(): bool
{
    return pluginActivate();
}

function ougcPrivateThreads_deactivate(): bool
{
    return pluginDeactivate();
}

function ougcPrivateThreads_install(): bool
{
    return pluginInstall();
}

function ougcPrivateThreads_is_installed(): bool
{
    return pluginIsInstalled();
}

function ougcPrivateThreads_uninstall(): bool
{
    return pluginUninstall();
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com )
if (!function_exists('control_object')) {
    function control_object(&$obj, $code)
    {
        static $cnt = 0;
        $newname = '_objcont_' . (++$cnt);
        $objserial = serialize($obj);
        $classname = get_class($obj);
        $checkstr = 'O:' . strlen($classname) . ':"' . $classname . '":';
        $checkstr_len = strlen($checkstr);
        if (substr($objserial, 0, $checkstr_len) == $checkstr) {
            $vars = array();
            // grab resources/object etc, stripping scope info from keys
            foreach ((array)$obj as $k => $v) {
                if ($p = strrpos($k, "\0")) {
                    $k = substr($k, $p + 1);
                }
                $vars[$k] = $v;
            }
            if (!empty($vars)) {
                $code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
            }
            eval('class ' . $newname . ' extends ' . $classname . ' {' . $code . '}');
            $obj = unserialize('O:' . strlen($newname) . ':"' . $newname . '":' . substr($objserial, $checkstr_len));
            if (!empty($vars)) {
                $obj->___setvars($vars);
            }
        }
        // else not a valid object or PHP serialize has changed
    }
}

if (!function_exists('control_db')) {
    // explicit workaround for PDO, as trying to serialize it causes a fatal error (even though PHP doesn't complain over serializing other resources)
    if ($GLOBALS['db'] instanceof AbstractPdoDbDriver) {
        $GLOBALS['AbstractPdoDbDriver_lastResult_prop'] = new ReflectionProperty('AbstractPdoDbDriver', 'lastResult');
        $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setAccessible(true);
        function control_db($code)
        {
            global $db;
            $linkvars = array(
                'read_link' => $db->read_link,
                'write_link' => $db->write_link,
                'current_link' => $db->current_link,
            );
            unset($db->read_link, $db->write_link, $db->current_link);
            $lastResult = $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->getValue($db);
            $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setValue($db, null); // don't let this block serialization
            control_object($db, $code);
            foreach ($linkvars as $k => $v) {
                $db->$k = $v;
            }
            $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setValue($db, $lastResult);
        }
    } elseif ($GLOBALS['db'] instanceof DB_SQLite) {
        function control_db($code)
        {
            global $db;
            $oldLink = $db->db;
            unset($db->db);
            control_object($db, $code);
            $db->db = $oldLink;
        }
    } else {
        function control_db($code)
        {
            control_object($GLOBALS['db'], $code);
        }
    }
}