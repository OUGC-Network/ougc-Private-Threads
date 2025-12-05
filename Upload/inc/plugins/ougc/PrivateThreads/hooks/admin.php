<?php

/***************************************************************************
 *
 *    ougc Private Thread plugin (/inc/plugins/ougc/PrivateThreads/admin_hooks.php)
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

namespace ougc\PrivateThreads\Hooks\Admin;

use MyBB;

use function ougc\PrivateThreads\Core\loadLanguage;
use function ougc\PrivateThreads\MyAlerts\getAvailableLocations;
use function ougc\PrivateThreads\MyAlerts\installLocation;
use function ougc\PrivateThreads\MyAlerts\MyAlertsIsIntegrable;

function admin_config_plugins_begin01(): void
{
    global $mybb, $lang, $page;

    if ($mybb->get_input('action') !== 'ougcPrivateThreads') {
		return;
    }

	loadLanguage();

    if ($mybb->get_input('no') || !MyAlertsIsIntegrable()) {
        admin_redirect('index.php?module=config-plugins');
    }

    if ($mybb->request_method != 'post') {
        $page->output_confirm_action(
            'index.php?module=config-plugins&amp;action=ougcPrivateThreads',
            $lang->ougcPrivateThreads_myalerts_confirm
        );
    }

    $availableLocations = getAvailableLocations();

    foreach ($availableLocations as $availableLocation) {
        installLocation($availableLocation);
    }

    flash_message($lang->ougcPrivateThreads_myalerts_success, 'success');

    admin_redirect('index.php?module=config-plugins');
}

function admin_config_plugins_deactivate(): void
{
    global $mybb, $page;

    if (
        $mybb->get_input('action') != 'deactivate' ||
        $mybb->get_input('plugin') != 'ougcPrivateThreads' ||
        !$mybb->get_input('uninstall', MyBB::INPUT_INT)
    ) {
		return;
    }

    if ($mybb->request_method != 'post') {
        $page->output_confirm_action(
            'index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin=ougcPrivateThreads'
        );
    }

    if ($mybb->get_input('no')) {
        admin_redirect('index.php?module=config-plugins');
    }
}