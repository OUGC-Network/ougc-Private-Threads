<?php

/***************************************************************************
 *
 *    ougc Private Thread plugin (/inc/plugins/ougc/PrivateThreads/admin.php)
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
use function ougc\PrivateThreads\Core\pluginLibraryInformation;
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
        'name' => 'ougc Private Thread',
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

function pluginActivate(): void
{
    global $PL, $lang, $cache;

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
        $PL->templates('ougcPrivateThreads', 'ougc Private Thread', $templates);
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
}

function pluginInstall(): void
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

function pluginUninstall(): void
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

    // Delete a version from the cache
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
}

function pluginLibraryLoad($checkRequirements = true): void
{
    global $PL, $lang;

    loadLanguage();

    if ($fileExists = file_exists(PLUGINLIBRARY)) {
        global $PL;

        $PL or require_once PLUGINLIBRARY;
    }

    if ($checkRequirements) {
        $pluginLibraryInformation = pluginLibraryInformation();

        if (!$fileExists || $PL->version < $pluginLibraryInformation->version) {
            flash_message(
                $lang->sprintf(
                    $lang->ougcPrivateThreadsPluginLibrary,
                    $pluginLibraryInformation->url,
                    $pluginLibraryInformation->version
                ),
                'error'
            );

            admin_redirect('index.php?module=config-plugins');
        }
    }
}

function dbTables(): array
{
    $tablesData = [];

    foreach (TABLES_DATA as $tableName => $tableColumns) {
        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            $tablesData[$tableName][$fieldName] = dbBuildFieldDefinition($fieldData);
        }

        foreach ($tableColumns as $fieldName => $fieldData) {
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

function dbVerifyTables(): void
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (dbTables() as $tableName => $tableColumns) {
        if ($db->table_exists($tableName)) {
            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($fieldName === 'primary_key' || $fieldName === 'unique_key') {
                    continue;
                }

                if ($db->field_exists($fieldName, $tableName)) {
                    $db->modify_column($tableName, "`{$fieldName}`", $fieldData);
                } else {
                    $db->add_column($tableName, $fieldName, $fieldData);
                }
            }
        } else {
            $query_string = "CREATE TABLE IF NOT EXISTS `{$db->table_prefix}{$tableName}` (";

            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($fieldName === 'primary_key') {
                    $query_string .= "PRIMARY KEY (`{$fieldData}`)";
                } elseif ($fieldName !== 'unique_key') {
                    $query_string .= "`{$fieldName}` {$fieldData},";
                }
            }

            $query_string .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query_string);
        }
    }

    dbVerifyIndexes();
}

function dbVerifyIndexes(): void
{
    global $db;

    foreach (dbTables() as $tableName => $tableColumns) {
        if (!$db->table_exists($tableName)) {
            continue;
        }

        if (isset($tableColumns['unique_key'])) {
            foreach ($tableColumns['unique_key'] as $key_name => $key_value) {
                if ($db->index_exists($tableName, $key_name)) {
                    continue;
                }

                $db->write_query(
                    "ALTER TABLE {$db->table_prefix}{$tableName} ADD UNIQUE KEY {$key_name} ({$key_value})"
                );
            }
        }
    }
}

function dbVerifyColumns(): void
{
    global $db;

    foreach (FIELDS_DATA as $tableName => $tableColumns) {
        if (!$db->table_exists($tableName)) {
            continue;
        }

        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            if ($db->field_exists($fieldName, $tableName)) {
                $db->modify_column($tableName, "`{$fieldName}`", dbBuildFieldDefinition($fieldData));
            } else {
                $db->add_column($tableName, $fieldName, dbBuildFieldDefinition($fieldData));
            }
        }
    }
}

function dbBuildFieldDefinition(array $fieldData): string
{
    $field_definition = '';

    $field_definition .= $fieldData['type'];

    if (isset($fieldData['size'])) {
        $field_definition .= "({$fieldData['size']})";
    }

    if (isset($fieldData['unsigned'])) {
        if ($fieldData['unsigned'] === true) {
            $field_definition .= ' UNSIGNED';
        } else {
            $field_definition .= ' SIGNED';
        }
    }

    if (!isset($fieldData['null'])) {
        $field_definition .= ' NOT';
    }

    $field_definition .= ' NULL';

    if (isset($fieldData['auto_increment'])) {
        $field_definition .= ' AUTO_INCREMENT';
    }

    if (isset($fieldData['default'])) {
        $field_definition .= " DEFAULT '{$fieldData['default']}'";
    }

    return $field_definition;
}