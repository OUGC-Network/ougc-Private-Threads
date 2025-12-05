<?php

/***************************************************************************
 *
 *    ougc Private Thread plugin (/inc/plugins/ougc/PrivateThreads/core.php)
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

namespace ougc\PrivateThreads\Core;

use MybbStuff_MyAlerts_AlertManager;
use MybbStuff_MyAlerts_AlertTypeManager;
use MybbStuff_MyAlerts_Entity_Alert;
use PMDataHandler;
use stdClass;

use function ougc\PrivateThreads\Admin\pluginInformation;

use const ougc\PrivateThreads\ROOT;

const TABLES_DATA = [
];

const FIELDS_DATA = [
    'threads' => [
        'ougcPrivateThreads_isPrivateThread' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'ougcPrivateThreads_userIDs' => [
            'type' => 'TEXT',
            'null' => true,
        ],
    ]
];

function loadLanguage(): void
{
    global $lang;

    isset($lang->ougcPrivateThreads) || $lang->load('ougcPrivateThreads');
}

function pluginLibraryInformation(): stdClass
{
    return (object)pluginInformation()['pl'];
}

function addHooks(string $namespace): void
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;

        if (substr($callable, 0, $namespaceWithPrefixLength) == $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, '', 0, $namespaceWithPrefixLength);

            $priority = substr($callable, -2);

            if (is_numeric(substr($hookName, -2))) {
                $hookName = substr($hookName, 0, -2);
            } else {
                $priority = 10;
            }

            $plugins->add_hook($hookName, $callable, $priority);
        }
    }
}

function getSetting(string $settingKey = '')
{
    global $mybb;

    return SETTINGS[$settingKey] ?? (
		$mybb->settings['ougcPrivateThreads_' . $settingKey] ?? false
	);
}

function getTemplateName(string $templateName = ''): string
{
    $templatePrefix = '';

    if ($templateName) {
        $templatePrefix = '_';
    }

    return "ougcPrivateThreads{$templatePrefix}{$templateName}";
}

function getTemplate(string $templateName = '', bool $enableHTMLComments = true): string
{
    global $templates;

    if (DEBUG) {
        $filePath = ROOT . "/templates/{$templateName}.html";

        $templateContents = file_get_contents($filePath);

        $templates->cache[getTemplateName($templateName)] = $templateContents;
    } elseif (my_strpos($templateName, '/') !== false) {
        $templateName = substr($templateName, strpos($templateName, '/') + 1);
    }

    return $templates->render(getTemplateName($templateName), true, $enableHTMLComments);
}

function buildWhereClauses(bool $fieldsPrefix = true, int $userID = 0): string
{
    global $mybb;

    if (!$userID) {
        $userID = (int)$mybb->user['uid'];
    }

    if ($fieldsPrefix) {
        $fieldsPrefix = 't.';
    } else {
        $fieldsPrefix = '';
    }

    $whereClauses = ["{$fieldsPrefix}ougcPrivateThreads_isPrivateThread=0", "{$fieldsPrefix}uid={$userID}"];

    $whereClausesSecondary = ["{$fieldsPrefix}ougcPrivateThreads_isPrivateThread=1"];

    if (getSetting('allowModeratorBypass') && is_moderator()) {
        $moderatorForumIDs = [];

        foreach (cache_forums() as $forumData) {
            if (is_moderator($forumData['fid'])) {
                $moderatorForumIDs[] = (int)$forumData['fid'];
            }
        }

        if ($moderatorForumIDs) {
            $moderatorForumIDs = implode("','", $moderatorForumIDs);

            $whereClauses[] = "{$fieldsPrefix}fid IN ('{$moderatorForumIDs}')";
        }
    }

    if (getSetting('enabledForums') !== -1) {
        $forumIDs = implode(
            ',',
            array_filter(array_map('intval', explode(',', getSetting('enabledForums'))))
        );

        if ($forumIDs) {
            $whereClausesSecondary[] = "{$fieldsPrefix}fid IN ({$forumIDs})";

            $whereClauses[] = "{$fieldsPrefix}fid NOT IN ({$forumIDs})";
        }
    }

    global $db;

    switch ($db->type) {
        case 'pgsql':
        case 'sqlite':
            $whereClausesSecondary[] = "(','||{$fieldsPrefix}ougcPrivateThreads_userIDs||',' LIKE '%,{$userID},%')";

            break;
        default:
            $whereClausesSecondary[] = "(CONCAT(',',{$fieldsPrefix}ougcPrivateThreads_userIDs,',') LIKE '%,{$userID},%')";

            break;
    }

    $whereClausesSecondary = implode(' AND ', $whereClausesSecondary);

    $whereClauses[] = "({$whereClausesSecondary})";

    return '(' . implode(' OR ', $whereClauses) . ')';
}

function isAllowedForum(int $forumID = 0, bool $verifyModeratorPermission = true): bool
{
    if (!getSetting('enabledForums') || ($verifyModeratorPermission && is_member(getSetting('allowGroupsBypass')))) {
        return false;
    }

    if ($forumID) {
        if (!isEnabledForum($forumID)) {
            return false;
        }

        if ($verifyModeratorPermission && getSetting('allowModeratorBypass') && is_moderator($forumID)) {
            return false;
        }
    }

    return true;
}

// Send a Private Message to a user (Copied from MyBB 1.7)
function sendPrivateMessage(array $privateMessageData, int $fromUserID = 0, bool $adminOverride = false): void
{
    global $mybb;

    $enabledNotificationTypes = array_flip(explode(',', getSetting('notificationTypes')));

    if (!isset($enabledNotificationTypes['pm']) || empty($mybb->settings['enablepms'])) {
        return;
    }

    if (!$privateMessageData['subject'] || !$privateMessageData['message'] || (!$privateMessageData['receivepms'] && !$adminOverride)) {
        return;
    }

    global $lang, $db, $session;

    if (defined('IN_ADMINCP')) {
        $lang->load('../messages');
    } else {
        $lang->load('messages');
    }

    static $dataHandler = null;

    if ($dataHandler === null) {
        require_once constant('MYBB_ROOT') . 'inc/datahandlers/pm.php';

        $dataHandler = new PMDataHandler();
    }

    // Build our final PM array
    $privateMessageData = [
        'subject' => $privateMessageData['subject'],
        'message' => $privateMessageData['message'],
        'icon' => -1,
        'fromid' => ($fromUserID == 0 ? (int)$mybb->user['uid'] : (max($fromUserID, 0))),
        'toid' => [$privateMessageData['touid']],
        'bccid' => [],
        'do' => '',
        'pmid' => '',
        'saveasdraft' => 0,
        'options' => [
            'signature' => 0,
            'disablesmilies' => 0,
            'savecopy' => 0,
            'readreceipt' => 0
        ]
    ];

    if (isset($mybb->session)) {
        $privateMessageData['ipaddress'] = $mybb->session->packedip;
    }

    $dataHandler->admin_override = (int)$adminOverride;

    $dataHandler->set_data($privateMessageData);

    if ($dataHandler->validate_pm()) {
        $dataHandler->insert_pm();
    }
}

function sendAlert(int $threadID, int $userID, int $authorID = 0): void
{
    global $alertType, $db;

    loadLanguage();

    $enabledNotificationTypes = array_flip(explode(',', getSetting('notificationTypes')));

    if (!isset($enabledNotificationTypes['myalerts']) || !class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        return;
    }

    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('ougcPrivateThreadsThread');

    if (!$alertType) {
        return;
    }

    $query = $db->simple_select(
        'alerts',
        'id',
        "object_id='{$threadID}' AND uid='{$userID}' AND unread=1 AND alert_type_id='{$alertType->getId()}'"
    );

    if ($db->fetch_field($query, 'id')) {
        return;
    }

    if ($alertType->getEnabled()) {
        $alert = new MybbStuff_MyAlerts_Entity_Alert();

        $alert->setType($alertType)->setUserId($userID)->setExtraDetails([
            'type' => $alertType->getId()
        ]);

        if ($threadID) {
            $alert->setObjectId($threadID);
        }

        if ($authorID) {
            $alert->setFromUserId($authorID);
        }

        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
    }
}

function isEnabledForum(int $forumID = 0): bool
{
    return (bool)is_member(getSetting('enabledForums'), ['usergroup' => $forumID, 'additionalgroups' => '']);
}

function allowedGroup(): bool
{
    global $mybb;

    return (bool)is_member(getSetting('allowedGroups'), $mybb->user);
}