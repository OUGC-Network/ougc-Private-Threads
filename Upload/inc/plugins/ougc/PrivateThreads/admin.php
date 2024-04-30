<?php

/***************************************************************************
 *
 *    OUGC Private Threads plugin (/inc/plugins/ougc/PrivateThreads/admin.php)
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

namespace ougc\PrivateThreads\Admin;


use DirectoryIterator;

use function ougc\PrivateThreads\Core\loadLanguage;
use function ougc\PrivateThreads\Core\pluginLibraryLoad;
use function ougc\PrivateThreads\MyAlerts\getAvailableLocations;
use function ougc\PrivateThreads\MyAlerts\getInstalledLocations;
use function ougc\PrivateThreads\MyAlerts\MyAlertsIsIntegrable;
use function ougc\PrivateThreads\MyAlerts\uninstallLocation;

use const ougc\PrivateThreads\ROOT;
use const ougc\PrivateThreads\Core\FIELDS_DATA;
use const ougc\PrivateThreads\Core\TABLES_DATA;

function pluginInformation(): array
{
    global $lang;

    loadLanguage();

    $pluginDescription = $lang->ougcPrivateThreadsDescription;

    if (pluginIsInstalled() && MyAlertsIsIntegrable()) {
        $pluginDescription .= $lang->ougcPrivateThreadsDescriptionMyAlerts;
    }

    return [
        'name' => 'OUGC Private Threads',
        'description' => $pluginDescription,
        'website' => 'https://ougc.network',
        'author' => 'Omar G.',
        'authorsite' => 'https://ougc.network',
        'version' => '1.8.36',
        'versioncode' => 1836,
        'compatibility' => '18*',
        'codename' => 'ougcPrivateThreads',
        'pl' => [
            'version' => 13,
            'url' => 'https://community.mybb.com/mods.php?action=view&pid=573'
        ],
        'myalerts' => [
            'version' => '2.0.4',
            'url' => 'https://community.mybb.com/thread-171301.html'
        ]
    ];
}

function pluginActivate(): bool
{
    global $PL, $lang, $cache, $db;

    pluginLibraryLoad();

    // Add settings group
    $settingsContents = file_get_contents(ROOT . '/settings.json');

    $settingsData = json_decode($settingsContents, true);

    foreach ($settingsData as $settingKey => &$settingData) {
        if (empty($lang->{"setting_ougcPrivateThreads_{$settingKey}"})) {
            continue;
        }

        if ($settingData['optionscode'] == 'select' || $settingData['optionscode'] == 'checkbox') {
            foreach ($settingData['options'] as $optionKey) {
                $settingData['optionscode'] .= "\n{$optionKey}={$lang->{"setting_ougcPrivateThreads_{$settingKey}_{$optionKey}"}}";
            }
        }

        $settingData['title'] = $lang->{"setting_ougcPrivateThreads_{$settingKey}"};
        $settingData['description'] = $lang->{"setting_ougcPrivateThreads_{$settingKey}_desc"};
    }

    $PL->settings(
        'ougcPrivateThreads',
        $lang->setting_group_ougcPrivateThreads,
        $lang->setting_group_ougcPrivateThreads_desc,
        $settingsData
    );

    // Add template group
    $templatesDirIterator = new DirectoryIterator(ROOT . '/templates');

    $templates = [];

    foreach ($templatesDirIterator as $template) {
        if (!$template->isFile()) {
            continue;
        }

        $pathName = $template->getPathname();

        $pathInfo = pathinfo($pathName);

        if ($pathInfo['extension'] === 'html') {
            $templates[$pathInfo['filename']] = file_get_contents($pathName);
        }
    }

    if ($templates) {
        $PL->templates('ougcPrivateThreads', 'OUGC Private Threads', $templates);
    }

    // Insert/update version into cache
    $pluginsCache = $cache->read('ougc_plugins');

    if (!$pluginsCache) {
        $pluginsCache = [];
    }

    $pluginInfo = pluginInformation();

    if (!isset($pluginsCache['PrivateThreads'])) {
        $pluginsCache['PrivateThreads'] = $pluginInfo['versioncode'];
    }

    dbVerifyTables();

    dbVerifyIndexes();

    dbVerifyColumns();

    /*~*~* RUN UPDATES START *~*~*/

    /*~*~* RUN UPDATES END *~*~*/

    $pluginsCache['PrivateThreads'] = $pluginInfo['versioncode'];

    $cache->update('ougc_plugins', $pluginsCache);

    return true;
}

function pluginDeactivate(): bool
{
    return false;
}

function pluginInstall(): bool
{
    global $cache;

    dbVerifyTables();

    dbVerifyIndexes();

    dbVerifyColumns();

    // MyAlerts
    $MyAlertLocationsInstalled = array_filter(
        getAvailableLocations(),
        '\\ougc\PrivateThreads\MyAlerts\\isLocationAlertTypePresent'
    );

    $cache->update('ougcPrivateThreads', [
        'MyAlertLocationsInstalled' => $MyAlertLocationsInstalled,
    ]);

    return true;
}

function pluginIsInstalled(): bool
{
    static $isInstalled = null;

    if ($isInstalled === null) {
        global $db;

        $isInstalled = false;

        foreach (FIELDS_DATA as $tableName => $fieldsData) {
            foreach ($fieldsData as $fieldName => $fieldData) {
                $isInstalled = (bool)$db->field_exists($fieldName, $tableName) ?? false;

                break;
            }

            break;
        }
    }

    return $isInstalled;
}

function pluginUninstall(): bool
{
    global $db, $PL, $cache;

    pluginLibraryLoad();

    foreach (TABLES_DATA as $tableName => $tableData) {
        if ($db->table_exists($tableName)) {
            $db->drop_table($tableName);
        }
    }

    foreach (FIELDS_DATA as $tableName => $fieldsData) {
        if ($db->table_exists($tableName)) {
            foreach ($fieldsData as $fieldName => $fieldData) {
                if ($db->field_exists($fieldName, $tableName)) {
                    $db->drop_column($tableName, $fieldName);
                }
            }
        }
    }

    //$PL->cache_delete('ougcPrivateThreads_packages');

    $PL->settings_delete('ougcPrivateThreads');

    $PL->templates_delete('ougcPrivateThreads');

    if (MyAlertsIsIntegrable()) {
        $installedLocations = getInstalledLocations();

        foreach ($installedLocations as $installedLocation) {
            uninstallLocation($installedLocation);
        }
    }

    // Delete version from cache
    $pluginsCache = (array)$cache->read('ougc_plugins');

    if (isset($pluginsCache['PrivateThreads'])) {
        unset($pluginsCache['PrivateThreads']);
    }

    if (!empty($pluginsCache)) {
        $cache->update('ougc_plugins', $pluginsCache);
    } else {
        $cache->delete('ougc_plugins');
    }

    $cache->delete('ougcPrivateThreadsAlerts');

    return true;
}

function dbTables(): array
{
    $tablesData = [];

    foreach (TABLES_DATA as $tableName => $fieldsData) {
        foreach ($fieldsData as $fieldName => $fieldData) {
            $fieldDefinition = '';

            if (!isset($fieldData['type'])) {
                continue;
            }

            $fieldDefinition .= $fieldData['type'];

            if (isset($fieldData['size'])) {
                $fieldDefinition .= "({$fieldData['size']})";
            }

            if (isset($fieldData['unsigned'])) {
                if ($fieldData['unsigned'] === true) {
                    $fieldDefinition .= ' UNSIGNED';
                } else {
                    $fieldDefinition .= ' SIGNED';
                }
            }

            if (!isset($fieldData['null'])) {
                $fieldDefinition .= ' NOT';
            }

            $fieldDefinition .= ' NULL';

            if (isset($fieldData['auto_increment'])) {
                $fieldDefinition .= ' AUTO_INCREMENT';
            }

            if (isset($fieldData['default'])) {
                $fieldDefinition .= " DEFAULT '{$fieldData['default']}'";
            }

            $tablesData[$tableName][$fieldName] = $fieldDefinition;
        }

        foreach ($fieldsData as $fieldName => $fieldData) {
            if (isset($fieldData['primary_key'])) {
                $tablesData[$tableName]['primary_key'] = $fieldName;
            }
            if ($fieldName === 'unique_key') {
                $tablesData[$tableName]['unique_key'] = $fieldData;
            }
        }
    }

    return $tablesData;
}

function dbVerifyTables(): bool
{
    global $db;

    $dbCollation = $db->build_create_table_collation();

    $tablePrefix = $db->table_prefix;

    foreach (dbTables() as $tableName => $tableData) {
        if ($db->table_exists($tableName)) {
            foreach ($tableData as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key' || $fieldName == 'unique_key') {
                    continue;
                }

                if ($db->field_exists($fieldName, $tableName)) {
                    $db->modify_column($tableName, "`{$fieldName}`", $fieldData);
                } else {
                    $db->add_column($tableName, $fieldName, $fieldData);
                }
            }
        } else {
            $queryString = "CREATE TABLE IF NOT EXISTS `{$tablePrefix}{$tableName}` (";

            foreach ($tableData as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key') {
                    $queryString .= "PRIMARY KEY (`{$fieldData}`)";
                } elseif ($fieldName !== 'unique_key') {
                    $queryString .= "`{$fieldName}` {$fieldData},";
                }
            }

            $queryString .= ") ENGINE=MyISAM{$dbCollation};";

            $db->write_query($queryString);
        }
    }

    dbVerifyIndexes();

    return true;
}

function dbVerifyIndexes(): bool
{
    global $db;

    $tablePrefix = $db->table_prefix;

    foreach (dbTables() as $tableName => $tableData) {
        if (!$db->table_exists($tableName)) {
            continue;
        }

        if (isset($tableData['unique_key'])) {
            foreach ($tableData['unique_key'] as $keyName => $keyValue) {
                if ($db->index_exists($tableName, $keyName)) {
                    continue;
                }

                $db->write_query("ALTER TABLE {$tablePrefix}{$tableName} ADD UNIQUE KEY {$keyName} ({$keyValue})");
            }
        }
    }

    return true;
}

function dbVerifyColumns(): bool
{
    global $db;

    $tablesData = [];

    foreach (FIELDS_DATA as $tableName => $fieldsData) {
        foreach ($fieldsData as $fieldName => $fieldData) {
            $fieldDefinition = '';

            if (!isset($fieldData['type'])) {
                continue;
            }

            $fieldDefinition .= $fieldData['type'];

            if (isset($fieldData['size'])) {
                $fieldDefinition .= "({$fieldData['size']})";
            }

            if (isset($fieldData['unsigned'])) {
                if ($fieldData['unsigned'] === true) {
                    $fieldDefinition .= ' UNSIGNED';
                } else {
                    $fieldDefinition .= ' SIGNED';
                }
            }

            if (!isset($fieldData['null'])) {
                $fieldDefinition .= ' NOT';
            }

            $fieldDefinition .= ' NULL';

            if (isset($fieldData['auto_increment'])) {
                $fieldDefinition .= ' AUTO_INCREMENT';
            }

            if (isset($fieldData['default'])) {
                $fieldDefinition .= " DEFAULT '{$fieldData['default']}'";
            }

            $tablesData[$tableName][$fieldName] = $fieldDefinition;
        }

        foreach ($tablesData as $tableName => $fieldsData) {
            if ($db->table_exists($tableName)) {
                foreach ($fieldsData as $fieldName => $fieldDefinition) {
                    if ($db->field_exists($fieldName, $tableName)) {
                        $db->modify_column($tableName, "`{$fieldName}`", $fieldDefinition);
                    } else {
                        $db->add_column($tableName, $fieldName, $fieldDefinition);
                    }
                }
            }
        }
    }

    return true;
}