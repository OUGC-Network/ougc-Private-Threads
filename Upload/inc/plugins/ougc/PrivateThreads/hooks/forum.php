<?php

/***************************************************************************
 *
 *    OUGC Private Threads plugin (/inc/plugins/ougc/PrivateThreads/forum_hooks.php)
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

namespace ougc\PrivateThreads\Hooks\Forum;

use DataHandler;
use MyBB;

use MyLanguage;

use postParser;

use function ougc\PrivateThreads\Core\buildWhereClauses;
use function ougc\PrivateThreads\Core\getSetting;
use function ougc\PrivateThreads\Core\allowedGroup;
use function ougc\PrivateThreads\Core\getTemplate;
use function ougc\PrivateThreads\Core\isAllowedForum;
use function ougc\PrivateThreads\Core\isEnabledForum;
use function ougc\PrivateThreads\Core\loadLanguage;
use function ougc\PrivateThreads\Core\sendAlert;
use function ougc\PrivateThreads\Core\sendPrivateMessage;
use function ougc\PrivateThreads\MyAlerts\myalertsIsIntegrable;
use function ougc\PrivateThreads\MyAlerts\registerMyalertsFormatters;

function global_start(): bool
{
    global $templatelist, $mybb;

    if (isset($templatelist)) {
        $templatelist .= ',';
    } else {
        $templatelist = '';
    }

    $templatelist .= ',';

    switch (constant('THIS_SCRIPT')) {
        case 'showthread.php':
            $templatelist .= ', ';
            break;
        case 'editpost.php':
        case 'newthread.php':
            $templatelist .= ', ';
            break;
        case 'forumdisplay.php':
            $templatelist .= ', ';
            break;
    }

    if (myalertsIsIntegrable() && !empty($mybb->user['uid'])) {
        registerMyalertsFormatters();
    }
    /*global $cache;
    $cache->update_most_replied_threads();
    $cache->update_most_viewed_threads();*/

    return true;
}

function myalerts_load_lang(): bool
{
    loadLanguage();

    return true;
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function newthread_end(): bool
{
    global $mybb, $fid, $bgcolor2, $ougcPrivateThreadsRow, $templates, $lang, $thread, $plugins, $db, $post;

    $ougcPrivateThreadsRow = '';

    $editThreadPage = $plugins->current_hook == 'editpost_end';

    $forumID = (int)$fid;

    if (!isAllowedForum($forumID, false)) {
        return false;
    }

    $mybb->user['uid'] = (int)$mybb->user['uid'];

    $isPrivateThread = isset($thread['ougcPrivateThreads_isPrivateThread']) ? (int)$thread['ougcPrivateThreads_isPrivateThread'] : 0;

    $userID = isset($thread['uid']) ? (int)$thread['uid'] : $mybb->user['uid'];

    if ($editThreadPage) {
        $firstPostID = !empty($thread['firstpost']) ? (int)$thread['firstpost'] : 0;

        if (empty($post['pid'])) {
            $post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));
        }

        $postID = !empty($post['pid']) ? (int)$post['pid'] : 0;

        if ($firstPostID !== $postID || !getSetting('allowStatusUpdate') && !$isPrivateThread) {
            return false;
        }

        if (
            isAllowedForum($forumID) &&
            $userID != $mybb->user['uid']
        ) {
            error_no_permission();
        }
    } elseif (!allowedGroup()) {
        return false;
    }

    $groupsCache = $mybb->cache->read('usergroups');

    loadLanguage();

    $userNamesInputValue = $checkedInput = $where_query = $disabledInput = '';

    $userNamesList = $whereClauses = [];

    if ($mybb->request_method === 'post') {
        if (getSetting('allowStatusUpdate')) {
            $isPrivateThread = $mybb->get_input('ougcPrivateThreadsFormCheckbox');
        }

        if ($isPrivateThread && $mybb->get_input('ougcPrivateThreadsUserNamesInput')) {
            $userNamesInputValue = implode(
                "','",
                array_map([$db, 'escape_string'],
                    array_map('my_strtolower', explode(',', $mybb->get_input('ougcPrivateThreadsUserNamesInput'))))
            );

            $whereClauses[] = "LOWER(username) IN ('{$userNamesInputValue}')";
        }
    } elseif (isset($thread['ougcPrivateThreads_userIDs'])) {
        $userIDs = implode(',', array_map('intval', explode(',', $thread['ougcPrivateThreads_userIDs'])));

        $whereClauses[] = "uid IN ({$userIDs})";
    }

    if ($whereClauses) {
        $whereClauses[] = "uid!='{$userID}'";

        $dbQuery = $db->simple_select('users', 'username', implode(' AND ', $whereClauses));

        while ($userNamesList[] = htmlspecialchars_uni((string)$db->fetch_field($dbQuery, 'username'))) {
        }

        $userNamesList = array_filter($userNamesList);

        if ($userNamesList) {
            $userNamesInputValue = implode(',', array_map('htmlspecialchars_uni', $userNamesList));
        }
    }

    if ($editThreadPage && !getSetting('allowStatusUpdate')) {
        $disabledInput = ' disabled="disabled"';
    }

    if ($isPrivateThread) {
        $checkedInput = ' checked="checked"';
    }

    $ougcPrivateThreadsRow = eval(getTemplate('form'));

    return true;
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function editpost_end(): bool
{
    return newthread_end();
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function datahandler_post_validate_thread(DataHandler &$dataHandler): DataHandler
{
    global $mybb, $lang, $db, $plugins;

    $editThreadPage = $plugins->current_hook === 'datahandler_post_validate_post';

    if ($editThreadPage) {
        $threadData = get_thread($dataHandler->data['tid']);
    }

    if ($editThreadPage && !getSetting('allowStatusUpdate')) {
        $mybb->input['ougcPrivateThreadsFormCheckbox'] = $threadData['ougcPrivateThreads_isPrivateThread'];
    }

    $forumID = (int)$dataHandler->data['fid'];

    if (
        !isAllowedForum($forumID, false) ||
        constant('THIS_SCRIPT') == 'xmlhttp.php'
    ) {
        return $dataHandler;
    }

    if ($editThreadPage) {
        $threadData = get_thread($dataHandler->data['tid']);

        $postData = get_post($dataHandler->data['pid']);

        if ($threadData['firstpost'] != $postData['pid']) {
            return $dataHandler;
        }

        if (
            isAllowedForum($forumID) &&
            (int)$threadData['uid'] != $dataHandler->data['edit_uid']
        ) {
            error_no_permission();
        }
    } elseif (!allowedGroup()) {
        return $dataHandler;
    }

    $dataHandler->ougcPrivateThreadsUsersList = '';

    if ($isPrivateThread = $mybb->get_input('ougcPrivateThreadsFormCheckbox', MyBB::INPUT_INT)) {
        loadLanguage();

        $userNamesInputValue = array_filter((array)explode(',', $mybb->get_input('ougcPrivateThreadsUserNamesInput')));

        if (!$userNamesInputValue) {
            if (!getSetting('allowEmptyUserList')) {
                $dataHandler->set_error($lang->ougcPrivateThreadsErrorEmpty);
            }
        } else {
            $userNamesList = implode(
                "','",
                array_map([$db, 'escape_string'], array_map('my_strtolower', $userNamesInputValue))
            );

            $dataHandler->data['uid'] = (int)$dataHandler->data['uid'];

            $dbQuery = $db->simple_select(
                'users',
                'uid',
                "LOWER(username) IN ('{$userNamesList}') AND uid!='{$dataHandler->data['uid']}'"
            );

            $userIDs = [];

            while ($userIDs[] = (int)$db->fetch_field($dbQuery, 'uid')) {
            }

            $userIDs = array_filter($userIDs);

            if (empty($userIDs)) {
                $dataHandler->set_error($lang->ougcPrivateThreadsErrorEmpty);
            }

            $dataHandler->ougcPrivateThreadsUsersList = implode(',', $userIDs);
        }
    }

    if (!$dataHandler->errors) {
        $dataHandler->ougcPrivateThreadsMarkAsPrivateThread = $isPrivateThread;
    }

    return $dataHandler;
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function datahandler_post_validate_post(DataHandler &$dataHandler): DataHandler
{
    if (!empty($dataHandler->first_post)) {
        datahandler_post_validate_thread($dataHandler);
    }

    return $dataHandler;
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function datahandler_post_insert_thread(DataHandler &$dataHandler): DataHandler
{
    global $mybb, $plugins;

    $isThreadUpdate = $plugins->current_hook === 'datahandler_post_update_thread';

    if (!isset($dataHandler->ougcPrivateThreadsMarkAsPrivateThread)) {
        return $dataHandler;
    }

    $forumID = (int)$dataHandler->data['fid'];

    if (!(
        isEnabledForum($forumID) &&
        (
            allowedGroup() ||
            (
                $isThreadUpdate &&
                is_moderator($dataHandler->data['fid']/*, 'caneditposts', $dataHandler->data['edit_uid']*/)
            )
        )
    )) {
        return $dataHandler;
    }

    if (isset($dataHandler->thread_insert_data)) {
        $dataHandler->thread_insert_data['ougcPrivateThreads_isPrivateThread'] = $dataHandler->ougcPrivateThreadsMarkAsPrivateThread;

        $dataHandler->thread_insert_data['ougcPrivateThreads_userIDs'] = $dataHandler->ougcPrivateThreadsUsersList;
    }

    if (isset($dataHandler->thread_update_data)) {
        $dataHandler->thread_update_data['ougcPrivateThreads_isPrivateThread'] = $dataHandler->ougcPrivateThreadsMarkAsPrivateThread;

        $dataHandler->thread_update_data['ougcPrivateThreads_userIDs'] = $dataHandler->ougcPrivateThreadsUsersList;
    }

    if ($isThreadUpdate) {
        $dataHandler->tid = (int)$dataHandler->tid;

        $dataHandler->data['uid'] = (int)$dataHandler->data['uid'];
    }

    // we delete thread subscriptions because we are making them private
    // ideally they shouldn't be deleted but the datahandler is a mess
    if ($isThreadUpdate && getSetting('deleteSubscriptions')) {
        global $db;

        $whereClauses = ["tid={$dataHandler->tid}", "uid!='{$dataHandler->data['uid']}'"];

        if ($dataHandler->thread_update_data['ougcPrivateThreads_userIDs']) {
            $whereClauses[] = "uid NOT IN ({$dataHandler->thread_update_data['ougcPrivateThreads_userIDs']})";
        }

        $db->delete_query('threadsubscriptions', implode(' AND ', $whereClauses));
    }

    if (getSetting('notificationTypes')) {
        $dataHandler->ougcPrivateThreadsNotificationUsers = '';

        if ($isThreadUpdate) {
            $threadData = get_thread($dataHandler->tid);

            $dataHandler->ougcPrivateThreadsNotificationUsers = $threadData['ougcPrivateThreads_userIDs'];
        }
    }

    return $dataHandler;
}

function datahandler_post_insert_thread_post(DataHandler &$dataHandler): DataHandler
{
    global $mybb, $lang, $parser;

    if (!isset($dataHandler->ougcPrivateThreadsNotificationUsers)) {
        return $dataHandler;
    }

    $previousUserIDs = explode(',', $dataHandler->ougcPrivateThreadsNotificationUsers);

    $newUserIDs = explode(',', $dataHandler->ougcPrivateThreadsUsersList);

    $newNotificationUserIDs = array_diff($newUserIDs, $previousUserIDs);

    if (!$newNotificationUserIDs) {
        return $dataHandler;
    }

    $sendPrivateMessage = my_strpos(',' . getSetting('notificationTypes') . ',', 'pm') !== false;

    $sendAlert = my_strpos(',' . getSetting('notificationTypes') . ',', 'myalerts') !== false;

    loadLanguage();

    if (!($parser instanceof postParser)) {
        require_once constant('MYBB_ROOT') . 'inc/class_parser.php';

        $parser = new postParser();
    }

    $threadSubject = $parser->parse_badwords($dataHandler->data['subject']);

    $threadUrl = get_thread_link($dataHandler->tid);

    $languageCache = [];

    foreach ($newNotificationUserIDs as $userID) {
        $userData = get_user($userID);

        if (empty($userData['uid'])) {
            continue;
        }

        if ($sendPrivateMessage) {
            if ($userData['language'] && $lang->language_exists($userData['language'])) {
                $userLanguage = $userData['language'];
            } elseif ($mybb->settings['bblanguage']) {
                $userLanguage = $mybb->settings['bblanguage'];
            } else {
                $userLanguage = 'english';
            }

            $userName = $dataHandler->data['username'];

            if ($userLanguage === $mybb->settings['bblanguage']) {
                $messageSubject = $lang->ougcPrivateThreads_notification_pm_subject;
                $messageBody = $lang->ougcPrivateThreads_notification_pm_message;

                if (!$dataHandler->data['username']) {
                    $userName = htmlspecialchars_uni($lang->guest);
                }
            } else {
                if (!isset($languageCache[$userLanguage]['emailsubject_forumsubscription'])) {
                    $userLanguageObject = new MyLanguage();

                    $userLanguageObject->set_path(constant('MYBB_ROOT') . 'inc/languages');

                    $userLanguageObject->set_language($userLanguage);

                    $userLanguageObject->load('messages');

                    $userLanguageObject->load('global');

                    $languageCache[$userLanguage]['emailsubject_forumsubscription'] = $userLanguageObject->emailsubject_forumsubscription;

                    $languageCache[$userLanguage]['email_forumsubscription'] = $userLanguageObject->email_forumsubscription;

                    $languageCache[$userLanguage]['guest'] = $userLanguageObject->guest;

                    unset($userLanguageObject);
                }

                $messageSubject = $languageCache[$userLanguage]['emailsubject_forumsubscription'];

                $messageBody = $languageCache[$userLanguage]['email_forumsubscription'];

                if (!$dataHandler->data['username']) {
                    $userName = $languageCache[$userLanguage]['guest'];
                }
            }

            sendPrivateMessage([
                'subject' => $lang->sprintf($messageSubject),
                'message' => $lang->sprintf(
                    $messageBody,
                    $userData['username'],
                    $dataHandler->data['username'],
                    $threadSubject,
                    $mybb->settings['bburl'],
                    $threadUrl,
                    $mybb->settings['bbname']
                ),
                'touid' => $userID
            ], -1, true);
        }

        if ($sendAlert) {
            sendAlert($dataHandler->tid, $userData['uid'], $dataHandler->data['uid']);
        }
    }

    return $dataHandler;
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function datahandler_post_update_thread(DataHandler &$dataHandler): DataHandler
{
    datahandler_post_insert_thread($dataHandler);

    datahandler_post_insert_thread_post($dataHandler);

    return $dataHandler;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function build_forumbits_forum(array &$forumData): array
{
    global $mybb, $plugins, $fcache, $db;

    if (!(
        isAllowedForum() &&
        (
            getSetting('fixForumLastPost') ||
            getSetting('fixForumCount')
        )
    )) {
        //$plugins->remove_hook('build_forumbits_forum', 'ougc\PrivateThreads\forumhooks\build_forumbits_forum');

        return $forumData;
    }

    static $forumCache = null;

    static $forumsToProcess = [];

    static $repliesCache = [];

    if ($forumCache === null) {
        $isModerator = is_moderator();

        $forumCache = [];

        foreach ($fcache as $forumCacheParent) {
            foreach ($forumCacheParent as $forumCacheParentParent) {
                foreach ($forumCacheParentParent as $forumCacheData) {
                    $forumID = (int)$forumCacheData['fid'];

                    if (
                        !isEnabledForum($forumID) ||
                        (
                            $isModerator &&
                            is_moderator($forumID)
                        )
                    ) {
                        continue;
                    }

                    $forumsToProcess[(int)$forumCacheData['fid']] = (int)$forumCacheData['lastposttid'];
                }
            }
        }

        if ($forumsToProcess) {
            $whereClauses = buildWhereClauses(false);

            $forumIDs = implode(',', array_keys($forumsToProcess));

            $dbQuery = $db->simple_select(
                'threads',
                'replies, tid, fid, subject, lastpost, lastposter, lastposteruid',
                "fid IN ({$forumIDs}) AND visible='1' AND closed NOT LIKE 'moved|%' AND ({$whereClauses})",
                ['order_by' => 'lastpost', 'order_dir' => 'desc']
            );

            while ($threadData = $db->fetch_array($dbQuery)) {
                $forumID = (int)$threadData['fid'];

                if (!$forumCache[$forumID]) {
                    unset($forumsToProcess[$forumID]);

                    $forumCache[$forumID] = $threadData;
                }
            }

            if ($forumsToProcess && getSetting('fixForumCount')) {
                $threadIDs = implode(',', array_values($forumsToProcess));

                $dbQuery = $db->simple_select(
                    'threads',
                    'fid, replies',
                    "tid IN ({$threadIDs})"
                );

                while ($threadData = $db->fetch_array($dbQuery)) {
                    $repliesCache[(int)$threadData['fid']] = (int)$threadData['replies'];
                }
            }
        }
    }

    if ((!$forumCache || empty($forumCache[$forumData['fid']])) && !isset($forumsToProcess[$forumData['fid']])) {
        return $forumData;
    }

    if (!empty($forumCache[$forumData['fid']])) {
        if ($forumData['lastposttid'] == $forumCache[$forumData['fid']]['tid']) {
            return $forumData;
        }

        if (getSetting('fixForumLastPost')) {
            $forumData['lastpost'] = (int)$forumCache[$forumData['fid']]['lastpost'];
            $forumData['lastposter'] = (string)$forumCache[$forumData['fid']]['lastposter'];
            $forumData['lastposteruid'] = (int)$forumCache[$forumData['fid']]['lastposteruid'];
            $forumData['lastposttid'] = (int)$forumCache[$forumData['fid']]['tid'];
            $forumData['lastpostsubject'] = (string)$forumCache[$forumData['fid']]['subject'];
        }

        if (getSetting('fixForumCount')) {
            $forumData['posts'] -= (int)$forumCache[$forumData['fid']]['replies'] + 1;
        }
    } else {
        if (!empty($repliesCache[$forumData['fid']])) {
            $forumData['posts'] -= $repliesCache[$forumData['fid']] + 1;
        }

        $forumData['lastpost'] = 0;
        $forumData['lastposter'] = '';
        $forumData['lastposteruid'] = 0;
        $forumData['lastposttid'] = 0;
        $forumData['lastpostsubject'] = '';
    }

    if (getSetting('fixForumCount')) {
        --$forumData['threads'];
    }

    return $forumData;
}

// usercp.php#1859
/*
$forum = $plugins->run_hooks("ougcPrivateThreads_build_forumbits_forum", $forum);
*/
function ougcPrivateThreads_build_forumbits_forum(array &$forumData): array
{
    global $mybb, $fcache, $db;

    $dbQuery = $db->simple_select('forums', '*', 'active != 0');

    while ($resultForumData = $db->fetch_array($dbQuery)) {
        $fcache[$resultForumData['pid']][$resultForumData['disporder']][$resultForumData['fid']] = $resultForumData;
    }

    build_forumbits_forum($forumData);

    return $forumData;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function forumdisplay_start(): bool
{
    global $db, $mybb;

    $forumID = $mybb->get_input('fid', MyBB::INPUT_INT);

    if (!isAllowedForum($forumID)) {
        return false;
    }

    /**--**/
    $forumPermissionsCache = forum_permissions();

    $forumPermissions = $forumPermissionsCache[$forumID];

    if (!$forumPermissions['canviewthreads']) {
        return false;
    }

    $visibleStates = [1];

    if ($forumPermissions['canviewdeletionnotice']) {
        $visibleStates[] = -1;
    }

    if (is_moderator($forumID)) {
        if (is_moderator($forumID, 'canviewdeleted')) {
            $visibleStates[] = -1;
        }

        if (is_moderator($forumID, 'canviewunapprove')) {
            $visibleStates[] = 0;
        }
    }

    $visibleCondition = 'visible IN (' . implode(',', array_unique($visibleStates)) . ')';

    if ($mybb->user['uid'] && $mybb->settings['showownunapproved']) {
        $visibleCondition .= " OR (t.visible=0 AND t.uid={$mybb->user['uid']})";
    }

    $threadVisibleOnly = "AND (t.{$visibleCondition})";
    /**--**/

    $whereClauses = buildWhereClauses();

    control_db(
        '
			function simple_select($table, $fields="*", $conditions="", $options=array())
			{
				static $done = false;
	
				if(!$done && $table == "threads t" && $fields == "COUNT(tid) AS threads" && my_strpos($conditions, "' . $threadVisibleOnly . '") !== false)
				{
					$conditions = str_replace("' . $threadVisibleOnly . '", "' . $threadVisibleOnly . ' AND ' . $whereClauses . '", $conditions);

					$done = true;
				}
	
				return parent::simple_select($table, $fields, $conditions, $options);
			}
		'
    );

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function forumdisplay_get_threads(): bool
{
    global $tvisibleonly, $db, $mybb, $fid;

    $forumID = (int)$fid;

    if (isAllowedForum($forumID)) {
        $whereClauses = buildWhereClauses();

        $tvisibleonly .= " AND {$whereClauses}";
    }

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function showthread_start(): bool
{
    global $thread, $mybb;

    $mybb->user['uid'] = (int)$mybb->user['uid'];

    $forumID = (int)$thread['fid'];

    if (
        !isAllowedForum($forumID) ||
        empty($thread['ougcPrivateThreads_isPrivateThread']) ||
        (int)$thread['uid'] == $mybb->user['uid'] ||
        my_strpos(',' . $thread['ougcPrivateThreads_userIDs'] . ',', ',' . $mybb->user['uid'] . ',') !== false
    ) {
        return false;
    }

    if (function_exists('archive_error_no_permission')) {
        archive_error_no_permission();
    }

    error_no_permission();

    return false;
}

// forums permissions: ok
function showthread_end(): bool
{
    global $mybb, $thread, $db, $lang, $templates, $theme, $ougcPrivateThreadsAllowedUsersList;

    $ougcPrivateThreadsAllowedUsersList = /*$ougcPrivateThreadsButton = */
        '';

    $thread['fisrtpost'] = (int)$thread['fisrtpost'];

    $forumID = (int)$thread['fid'];

    if (!(
        isEnabledForum($forumID) &&
        !empty($thread['ougcPrivateThreads_isPrivateThread']) &&
        getSetting('showUserList')
    )) {
        return false;
    }

    loadLanguage();

    $allowedUsersList = $commaSeparator = '';

    if (!empty($thread['ougcPrivateThreads_userIDs'])) {
        $userIDs = implode(',', array_map('intval', explode(',', $thread['ougcPrivateThreads_userIDs'])));

        $whereClauses = ["uid IN ({$userIDs})", "uid!='{$thread['uid']}'"];

        $dbQuery = $db->simple_select(
            'users',
            'uid, username, usergroup, displaygroup',
            implode(' AND ', $whereClauses)
        );

        while ($userData = $db->fetch_array($dbQuery)) {
            $userData['username'] = htmlspecialchars_uni($userData['username']);

            $userNameFormatted = format_name(
                $userData['username'],
                $userData['usergroup'],
                $userData['displaygroup']
            );

            $userProfileLink = get_profile_link($userData['uid']);

            $allowedUsersList .= eval(getTemplate('showThreadUserListItem'));

            $commaSeparator = $lang->comma;
        }
    } else {
        $allowedUsersList = $lang->ougcPrivateThreadsShowThreadAllowedUsersNone;
    }

    if ($allowedUsersList) {
        $ougcPrivateThreadsAllowedUsersList = eval(getTemplate('showThreadUserList'));
    }

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function newreply_start(): bool
{
    global $thread, $mybb, $db, $plugins;

    showthread_start();

    // The following code deals with quotes

    if (!isAllowedForum() || $plugins->current_hook !== 'newreply_start') {
        return false;
    }

    $whereClauses = buildWhereClauses();

    control_db(
        '
		function query($string, $hide_errors=0, $write_query=0)
		{
			static $done = false;

			if(!$done && my_strpos($string, "SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline, u.username AS userusername") !== false)
			{
				$string = str_replace("p.pid", "' . $whereClauses . ' AND p.pid", $string);

				$done = true;
			}

			return parent::query($string, $hide_errors, $write_query);
		}
	'
    );

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function newreply_do_newreply_start(): bool
{
    newreply_start();

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function xmlhttp_get_multiquoted_intermediate(): bool
{
    global $unviewable_forums, $mybb, $inactiveforums;

    if (!isAllowedForum()) {
        return false;
    }

    $whereClauses = buildWhereClauses();

    $unviewable_forums .= ' AND ' . $whereClauses;

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function syndication_start(): bool
{
    global $db, $mybb;

    if (!isAllowedForum()) {
        return false;
    }

    $whereClauses = buildWhereClauses(false);

    control_db(
        '
		function simple_select($table, $fields="*", $conditions="", $options=array())
		{
			static $done = false;

			if(!$done && $table == "threads" && $fields == "subject, tid, dateline, firstpost")
			{
				$conditions .= " AND ' . $whereClauses . '";

				$done = true;
			}

			return parent::simple_select($table, $fields, $conditions, $options);
		}
	'
    );

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function portal_start(): bool
{
    global $db, $settings;

    if (!isAllowedForum()) {
        return false;
    }

    $whereClauses = buildWhereClauses();

    control_db(
        '
		function simple_select($table, $fields="*", $conditions="", $options=array())
		{
			static $done = false;

			if(!$done && $table == "threads t" && $fields == "COUNT(t.tid) AS threads")
			{
				$conditions .= " AND ' . $whereClauses . '";

				$done = true;
			}

			return parent::simple_select($table, $fields, $conditions, $options);
		}

		function query($string, $hide_errors=0, $write_query=0)
		{
			static $done = false;

			if(!$done && my_strpos($string, "SELECT t.*, t.username AS threadusername, u.username, u.avatar, u.avatardimensions") !== false)
			{
				$string = str_replace("t.tid", "' . $whereClauses . ' AND t.tid", $string);

				$done = true;
			}

			return parent::query($string, $hide_errors, $write_query);
		}
	'
    );

    if ($settings['portal_showdiscussions'] && $settings['portal_showdiscussionsnum'] && $settings['portal_excludediscussion'] != -1) {
        control_db(
            '
			function query($string, $hide_errors=0, $write_query=0)
			{
				static $done = false;
	
				if(!$done && my_strpos($string, "SELECT t.tid, t.fid, t.uid, t.lastpost, t.lastposteruid, t.lastposter") !== false)
				{
					$string = str_replace("1=1", "' . $whereClauses . ' AND 1=1", $string);

					$done = true;
				}
	
				return parent::query($string, $hide_errors, $write_query);
			}
		'
        );
    }

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function archive_start(): bool
{
    global $db, $mybb, $action;

    if ($action !== 'forum' || !isAllowedForum()) {
        return false;
    }

    $whereClauses = buildWhereClauses(false);

    control_db(
        '
		function simple_select($table, $fields="*", $conditions="", $options=array())
		{
			static $done = false;

			if(!$done && $table == "threads" && $fields == "COUNT(tid) AS threads")
			{
				$conditions .= " AND ' . $whereClauses . '";

				$done = true;
			}

			static $done2 = 0;

			if($done2 < 2 && $table == "threads" && my_strpos($conditions, " AND sticky=") !== false)
			{
				$conditions .= " AND ' . $whereClauses . '";

				++$done2;
			}

			return parent::simple_select($table, $fields, $conditions, $options);
		}
	'
    );

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function printthread_start(): bool
{
    showthread_start();

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function sendthread_do_sendtofriend_start(): bool
{
    showthread_start();

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function sendthread_start(): bool
{
    showthread_start();

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function ratethread_start(): bool
{
    showthread_start();

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function archive_thread_start(): bool
{
    showthread_start();

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function attachment_start(): bool
{
    global $attachment, $thread;

    $postData = get_post($attachment['pid']);

    $thread = get_thread($postData['tid']);

    if (empty($thread)) {
        return false;
    }

    showthread_start();

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function report_type(): bool
{
    global $report_type, $post, $thread, $mybb, $error, $lang;

    if ($report_type !== 'post') {
        return false;
    }

    $mybb->user['uid'] = (int)$mybb->user['uid'];

    $threadData = get_thread($post['tid']);

    $forumID = (int)$threadData['fid'];

    if (
        !isAllowedForum($forumID) ||
        empty($threadData['ougcPrivateThreads_isPrivateThread']) ||
        (int)$threadData['uid'] == $mybb->user['uid'] ||
        my_strpos(',' . $threadData['ougcPrivateThreads_userIDs'] . ',', ',' . $mybb->user['uid'] . ',') !== false
    ) {
        return false;
    }

    $error = $lang->sprintf($lang->error_invalid_report, $report_type);
    //showthread_start();

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function reputation_do_add_start(): bool
{
    global $thread;

    if (!empty($thread['tid'])) {
        showthread_start();
    }

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function reputation_add_start(): bool
{
    reputation_do_add_start();

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function reputation_start(): bool
{
    global $mybb, $db;

    if ($mybb->get_input('action') || !isAllowedForum()) {
        return false;
    }

    $whereClauses = buildWhereClauses();

    control_db(
        '
		function query($string, $hide_errors=0, $write_query=0)
		{
			static $done = false;

			if(!$done && my_strpos($string, "p.pid, p.uid, p.fid, p.visible, p.message, t.tid, t.subject") !== false)
			{
				$string = str_replace("p.pid IN", "' . $whereClauses . ' AND p.pid IN", $string);

				$done = true;
			}

			return parent::query($string, $hide_errors, $write_query);
		}
	'
    );

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function usercp_notepad_end(): bool
{
    global $mybb, $db;

    if ($mybb->get_input('action') || !isAllowedForum()) {
        return false;
    }

    $whereClauses = buildWhereClauses();

    control_db(
        '
		function query($string, $hide_errors=0, $write_query=0)
		{
			static $done = false;

			if(!$done && my_strpos($string, "SELECT s.*, t.*, t.username AS threadusername, u.username") !== false)
			{
				$string = str_replace("s.uid=", "' . $whereClauses . ' AND s.uid=", $string);

				$done = true;
			}

			return parent::query($string, $hide_errors, $write_query);
		}
	'
    );

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function usercp_subscriptions_start(): bool
{
    global $mybb, $db;

    if (!isAllowedForum()) {
        return false;
    }

    $whereClauses = buildWhereClauses();

    control_db(
        '
		function query($string, $hide_errors=0, $write_query=0)
		{
			static $done = false;

			if(!$done && my_strpos($string, "SELECT COUNT(ts.tid) as threads") !== false)
			{
				$string = str_replace("ts.uid", "' . $whereClauses . ' AND ts.uid", $string);

				$done = true;
			}

			static $done2 = false;

			if(!$done2 && my_strpos($string, "SELECT s.*, t.*, t.username AS threadusername, u.username") !== false)
			{
				$string = str_replace("s.uid=", "' . $whereClauses . ' AND s.uid=", $string);

				$done2 = true;
			}

			return parent::query($string, $hide_errors, $write_query);
		}
	'
    );

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function usercp_attachments_start(): bool
{
    global $db, $mybb;

    if (!isAllowedForum()) {
        return false;
    }

    $whereClauses = buildWhereClauses();

    $mybb->user['uid'] = (int)$mybb->user['uid'];

    control_db(
        '
		function simple_select($table, $fields="*", $conditions="", $options=array())
		{
			static $done = false;

			if(!$done && $table == "attachments" && $fields == "SUM(filesize) AS ausage, COUNT(aid) AS acount")
			{
				$table .= " a LEFT JOIN ' . $db->table_prefix . 'posts p ON (p.pid=a.pid) LEFT JOIN ' . $db->table_prefix . 'threads t ON (t.tid=p.tid)";
				$fields = "SUM(a.filesize) AS ausage, COUNT(a.aid) AS acount";
				$conditions = "a.uid=' . $mybb->user['uid'] . ' AND ' . $whereClauses . '";

				$done = true;
			}

			return parent::simple_select($table, $fields, $conditions, $options);
		}

		function query($string, $hide_errors=0, $write_query=0)
		{
			static $done = false;

			if(!$done && my_strpos($string, "SELECT a.*, p.subject, p.dateline, t.tid, t.subject AS threadsubject") !== false)
			{
				$string = str_replace("WHERE a.uid=", "WHERE ' . $whereClauses . ' AND a.uid=", $string);

				$done = true;
			}

			return parent::query($string, $hide_errors, $write_query);
		}
	'
    );

    return true;
}


function usercp_do_attachments_start(): bool
{
    global $mybb, $db, $thread;

    $attachmentIDs = implode(',', array_map('intval', $mybb->get_input('attachments', MyBB::INPUT_ARRAY)));

    if (!$attachmentIDs) {
        return false;
    }

    $dbQuery = $db->simple_select(
        'attachments',
        'DISTINCT pid',
        "aid IN ({$attachmentIDs}) AND uid={$mybb->user['uid']}"
    );

    while ($attachment = $db->fetch_array($dbQuery)) {
        $postData = get_post($attachment['pid']);

        $threadData = get_thread($postData['tid']);

        if (!empty($threadData['tid'])) {
            showthread_start();
        }
    }

    return true;
}

function search_end(): bool
{
    global $ougcPrivateThreadsSearch, $mybb, $templates, $lang;

    $ougcPrivateThreadsSearch = '';

    if (!isAllowedForum(0, false) || !is_member(
            getSetting('enableSearchSystem')
        )) {
        return false;
    }

    loadLanguage();

    $ougcPrivateThreadsSearch = eval(getTemplate('search'));

    return true;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function search_do_search_process(): bool
{
    global $searcharray, $db, $mybb;

    $whereClauses = [];

    if (
        isAllowedForum(0, false) &&
        is_member(getSetting('enableSearchSystem')) &&
        $mybb->get_input('onlyPrivateThreads', MyBB::INPUT_INT)
    ) {
        $whereClauses[] = 't.ougcPrivateThreads_isPrivateThread=1';
    }

    if (isAllowedForum()) {
        $userID = (int)$searcharray['uid'];

        $whereClauses[] = buildWhereClauses(true, $userID);
    }

    if (!$whereClauses) {
        return false;
    }

    $whereClauses = implode(' AND ', $whereClauses);

    // $searcharray['querycache'] works for threads, not posts..
    if ($searcharray['resulttype'] === 'posts') {
        $psotIDs = implode("','", array_filter(array_map('intval', explode(',', $searcharray['posts']))));

        $dbQuery = $db->simple_select(
            'posts p LEFT JOIN ' . $db->table_prefix . 'threads t ON (t.tid=p.tid)',
            'p.pid',
            "p.pid IN ('{$psotIDs}') AND {$whereClauses}"
        );

        $psotIDs = [];

        while ($psotIDs[] = (int)$db->fetch_field($dbQuery, 'pid')) {
        }

        $searcharray['posts'] = implode(',', array_filter($psotIDs));
    }

    if ($searcharray['querycache']) {
        $searcharray['querycache'] .= ' AND ';
    }

    $searcharray['querycache'] .= $db->escape_string($whereClauses);

    return true;
}

function search_results_start(): bool
{
    global $settings, $search, $db;

    if (isAllowedForum(0, false) && getSetting('prefixClassName')) {
        global $templates;

        if ($search['resulttype'] == 'posts') {
            control_db(
                '
				function query($string, $hide_errors=0, $write_query=0)
				{
					static $done = false;
		
					if(!$done && my_strpos($string, "SELECT p.*, u.username AS userusername, t.subject AS thread_subject") !== false)
					{
						$string = str_replace("p.*", "t.ougcPrivateThreads_isPrivateThread, p.*", $string);

						$done = true;
					}
		
					return parent::query($string, $hide_errors, $write_query);
				}
			'
            );
        }

        $templates->cache['search_results_threads_inlinecheck'] = str_replace(
            '{$bgcolor}',
            '{$bgcolor}<!--ougcPrivateThreadsThreadTrow-->',
            $templates->cache['search_results_threads_inlinecheck']
        );

        $templates->cache['search_results_posts_inlinecheck'] = str_replace(
            '{$bgcolor}',
            '{$bgcolor}<!--ougcPrivateThreadsThreadTrow-->',
            $templates->cache['search_results_posts_inlinecheck']
        );
    }

    return true;
}

function search_results_thread(&$postData = null): bool
{
    global $bgcolor, $mybb, $inline_mod_checkbox, $lang, $templates;

    if (is_array($postData)) {
        $thread = &$postData;
    } else {
        global $thread;
    }

    $thread['ougcPrivateThreadsThreadClass'] = '';

    $thread['ougcPrivateThreadsThreadPrefix'] = '';

    if (getSetting('assignPrefix') && $thread['ougcPrivateThreads_isPrivateThread']) {
        loadLanguage();

        $thread['ougcPrivateThreadsThreadPrefix'] = eval(getTemplate('prefix'));
    }

    if (!getSetting('prefixClassName')) {
        return false;
    }

    $styleClassName = '';

    if ($thread['ougcPrivateThreads_isPrivateThread']) {
        $styleClassName = ' ' . htmlspecialchars_uni(getSetting('prefixClassName'));

        $bgcolor .= $styleClassName;

        $thread['ougcPrivateThreadsThreadClass'] = $styleClassName;
    }

    if ($inline_mod_checkbox) {
        $inline_mod_checkbox = str_replace(
            '<!--ougcPrivateThreadsThreadTrow-->',
            $styleClassName,
            $inline_mod_checkbox
        );
    }

    return true;
}

function search_results_post(): bool
{
    global $post;

    search_results_thread($post);

    return true;
}

function forumdisplay_thread(): bool
{
    search_results_thread();

    return true;
}