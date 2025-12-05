<?php

/***************************************************************************
 *
 *    ougc Private Thread plugin (/inc/plugins/ougc/PrivateThreads/forum_hooks.php)
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

use PostDataHandler;
use MyBB;
use MyLanguage;
use postParser;

use function ougc\PrivateThreads\Core\buildWhereClauses;
use function ougc\PrivateThreads\Core\control_db;
use function ougc\PrivateThreads\Core\getSetting;
use function ougc\PrivateThreads\Core\allowedGroup;
use function ougc\PrivateThreads\Core\getTemplate;
use function ougc\PrivateThreads\Core\isAllowedForum;
use function ougc\PrivateThreads\Core\isEnabledForum;
use function ougc\PrivateThreads\Core\loadLanguage;
use function ougc\PrivateThreads\Core\sendAlert;
use function ougc\PrivateThreads\Core\sendPrivateMessage;
use function ougc\PrivateThreads\MyAlerts\myalertsIsIntegrable;
use function ougc\PrivateThreads\MyAlerts\registerMyAlertsFormatters;

function global_start(): void
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
            $templatelist .= ', ougcPrivateThreads_showThreadUserList, ougcPrivateThreads_showThreadUserListItem';
            break;
        case 'editpost.php':
        case 'newthread.php':
            $templatelist .= ', ougcPrivateThreads_form';
            break;
        case 'forumdisplay.php':
        case 'search.php':
            $templatelist .= ', ougcPrivateThreads_prefix, ougcPrivateThreads_search';
            break;
    }

    if (myalertsIsIntegrable() && !empty($mybb->user['uid'])) {
        registerMyAlertsFormatters();
    }
}

function myalerts_load_lang(): void
{
    loadLanguage();
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function newthread_end(): void
{
    global $mybb, $fid, $bgcolor2, $ougcPrivateThreadsRow, $lang, $thread, $plugins, $db, $post;

    $ougcPrivateThreadsRow = '';

    $editThreadPage = $plugins->current_hook == 'editpost_end';

    $forumID = (int)$fid;

    if (!isAllowedForum($forumID, false)) {
        return;
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
            return;
        }

        if (
            isAllowedForum($forumID) &&
            $userID != $mybb->user['uid']
        ) {
            error_no_permission();
        }
    } elseif (!allowedGroup()) {
        return;
    }

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

        while ($userName = $db->fetch_field($dbQuery, 'username')) {
            $userNamesList[] = htmlspecialchars_uni($userName);
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
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function editpost_end(): void
{
    newthread_end();
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function datahandler_post_validate_thread(PostDataHandler &$dataHandler): PostDataHandler
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

    global $ougcPrivateThreadsMarkAsPrivateThread, $ougcPrivateThreadsUsersList;

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

            while ($userID = $db->fetch_field($dbQuery, 'uid')) {
                $userIDs[] = (int)$userID;
            }

            $userIDs = array_filter($userIDs);

            if (empty($userIDs)) {
                $dataHandler->set_error($lang->ougcPrivateThreadsErrorEmpty);
            }

            $ougcPrivateThreadsUsersList = implode(',', $userIDs);
        }
    }

    if (!$dataHandler->errors) {
        $ougcPrivateThreadsMarkAsPrivateThread = $isPrivateThread;
    }

    return $dataHandler;
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function datahandler_post_validate_post(PostDataHandler &$dataHandler): PostDataHandler
{
    if (!empty($dataHandler->first_post)) {
        datahandler_post_validate_thread($dataHandler);
    }

    return $dataHandler;
}

// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function datahandler_post_insert_thread(PostDataHandler &$dataHandler): PostDataHandler
{
    global $plugins;
    global $ougcPrivateThreadsMarkAsPrivateThread, $ougcPrivateThreadsNotificationUsers, $ougcPrivateThreadsUsersList;

    $isThreadUpdate = $plugins->current_hook === 'datahandler_post_update_thread';

    if (!isset($ougcPrivateThreadsMarkAsPrivateThread)) {
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
        $dataHandler->thread_insert_data['ougcPrivateThreads_isPrivateThread'] = $ougcPrivateThreadsMarkAsPrivateThread;

        $dataHandler->thread_insert_data['ougcPrivateThreads_userIDs'] = $ougcPrivateThreadsUsersList;
    }

    if (isset($dataHandler->thread_update_data)) {
        $dataHandler->thread_update_data['ougcPrivateThreads_isPrivateThread'] = $ougcPrivateThreadsMarkAsPrivateThread;

        $dataHandler->thread_update_data['ougcPrivateThreads_userIDs'] = $ougcPrivateThreadsUsersList;
    }

    if ($isThreadUpdate) {
        $dataHandler->tid = (int)$dataHandler->tid;

        $dataHandler->data['uid'] = (int)$dataHandler->data['uid'];
    }

    // we delete thread subscriptions because we are making them private
    // ideally they shouldn't be deleted, but the datahandler is a mess
    if ($isThreadUpdate && getSetting('deleteSubscriptions')) {
        global $db;

        $whereClauses = ["tid={$dataHandler->tid}", "uid!='{$dataHandler->data['uid']}'"];

        if ($dataHandler->thread_update_data['ougcPrivateThreads_userIDs']) {
            $whereClauses[] = "uid NOT IN ({$dataHandler->thread_update_data['ougcPrivateThreads_userIDs']})";
        }

        $db->delete_query('threadsubscriptions', implode(' AND ', $whereClauses));
    }

    if (getSetting('notificationTypes')) {
        $ougcPrivateThreadsNotificationUsers = '';

        if ($isThreadUpdate) {
            $threadData = get_thread($dataHandler->tid);

            $ougcPrivateThreadsNotificationUsers = $threadData['ougcPrivateThreads_userIDs'];
        }
    }

    return $dataHandler;
}

function datahandler_post_insert_thread_post(PostDataHandler &$dataHandler): PostDataHandler
{
    global $mybb, $lang, $parser;
    global $ougcPrivateThreadsNotificationUsers, $ougcPrivateThreadsUsersList;

    if (!isset($ougcPrivateThreadsNotificationUsers)) {
        return $dataHandler;
    }

    $previousUserIDs = explode(',', $ougcPrivateThreadsNotificationUsers);

    $newUserIDs = explode(',', $ougcPrivateThreadsUsersList);

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
function datahandler_post_update_thread(PostDataHandler &$dataHandler): PostDataHandler
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
    global $fcache, $db;

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

                if (empty($forumCache[$forumID])) {
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
    global $fcache, $db;

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
function forumdisplay_start(): void
{
    global $mybb;

    $forumID = $mybb->get_input('fid', MyBB::INPUT_INT);

    if (!isAllowedForum($forumID)) {
        return;
    }

    /**--**/
    $forumPermissionsCache = forum_permissions();

    $forumPermissions = $forumPermissionsCache[$forumID];

    if (!$forumPermissions['canviewthreads']) {
        return;
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
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
// groups permissions: ok
function forumdisplay_get_threads(): void
{
    global $tvisibleonly, $fid;

    $forumID = (int)$fid;

    if (isAllowedForum($forumID)) {
        $whereClauses = buildWhereClauses();

        $tvisibleonly .= " AND {$whereClauses}";
    }
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function showthread_start(): void
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
        return;
    }

    if (function_exists('archive_error_no_permission')) {
        archive_error_no_permission();
    }

    error_no_permission();
}

// forums permissions: ok
function showthread_end(): void
{
    global $thread, $db, $lang, $theme, $ougcPrivateThreadsAllowedUsersList;

    $ougcPrivateThreadsAllowedUsersList = /*$ougcPrivateThreadsButton = */
        '';

    $thread['firstpost'] = (int)$thread['firstpost'];

    $forumID = (int)$thread['fid'];

    if (!(
        isEnabledForum($forumID) &&
        !empty($thread['ougcPrivateThreads_isPrivateThread']) &&
        getSetting('showUserList')
    )) {
        return;
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
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function newreply_start(): void
{
    global $plugins;

    showthread_start();

    // The following code deals with quotes

    if (!isAllowedForum() || $plugins->current_hook !== 'newreply_start') {
        return;
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
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function newreply_do_newreply_start(): void
{
    newreply_start();
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function xmlhttp_get_multiquoted_intermediate(): void
{
    global $unviewable_forums;

    if (!isAllowedForum()) {
        return;
    }

    $whereClauses = buildWhereClauses();

    $unviewable_forums .= ' AND ' . $whereClauses;
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function syndication_start(): void
{
    if (!isAllowedForum()) {
        return;
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
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function portal_start(): void
{
    global $settings;

    if (!isAllowedForum()) {
        return;
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
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function archive_start(): void
{
    global $action;

    if ($action !== 'forum' || !isAllowedForum()) {
        return;
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
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function printthread_start(): void
{
    showthread_start();
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function sendthread_do_sendtofriend_start(): void
{
    showthread_start();
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function sendthread_start(): void
{
    showthread_start();
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function ratethread_start(): void
{
    showthread_start();
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function archive_thread_start(): void
{
    showthread_start();
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function attachment_start(): void
{
    global $attachment, $thread;

    $postData = get_post($attachment['pid']);

    $thread = get_thread($postData['tid']);

    if (empty($thread)) {
        return;
    }

    showthread_start();
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function report_type(): void
{
    global $report_type, $post, $mybb, $error, $lang;

    if ($report_type !== 'post') {
        return;
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
        return;
    }

    $error = $lang->sprintf($lang->error_invalid_report, $report_type);
    //showthread_start();
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function reputation_do_add_start(): void
{
    global $thread;

    if (!empty($thread['tid'])) {
        showthread_start();
    }
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function reputation_add_start(): void
{
    reputation_do_add_start();
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function reputation_start(): void
{
    global $mybb;

    if ($mybb->get_input('action') || !isAllowedForum()) {
        return;
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
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function usercp_notepad_end(): void
{
    global $mybb;

    if ($mybb->get_input('action') || !isAllowedForum()) {
        return;
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
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function usercp_subscriptions_start(): void
{
    if (!isAllowedForum()) {
        return;
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
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function usercp_attachments_start(): void
{
    global $db, $mybb;

    if (!isAllowedForum()) {
        return;
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
}


function usercp_do_attachments_start(): void
{
    global $mybb, $db;

    $attachmentIDs = implode(',', array_map('intval', $mybb->get_input('attachments', MyBB::INPUT_ARRAY)));

    if (!$attachmentIDs) {
        return;
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
}

function search_end(): void
{
    global $ougcPrivateThreadsSearch, $lang;

    $ougcPrivateThreadsSearch = '';

    if (!isAllowedForum(0, false) || !is_member(
            getSetting('enableSearchSystem')
        )) {
        return;
    }

    loadLanguage();

    $ougcPrivateThreadsSearch = eval(getTemplate('search'));
}

// author permission: ok
// moderator permission: ok
// forums permissions: ok
function search_do_search_process11(): void
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
        return;
    }

    $whereClauses = implode(' AND ', $whereClauses);

    if (!empty($searcharray['threads'])) {
        $threadIDs = implode("','", array_filter(array_map('intval', explode(',', $searcharray['threads']))));

        $dbQuery = $db->simple_select(
            'threads t',
            't.tid',
            "t.tid IN ('{$threadIDs}') AND {$whereClauses}"
        );

        $threadIDs = [];

        while ($threadID = $db->fetch_field($dbQuery, 'tid')) {
            $threadIDs[] = (int)$threadID;
        }

        $searcharray['threads'] = implode(',', array_filter($threadIDs));
    } elseif (!empty($searcharray['posts'])) {
        $postIDs = implode("','", array_filter(array_map('intval', explode(',', $searcharray['posts']))));

        $dbQuery = $db->simple_select(
            'posts p LEFT JOIN ' . $db->table_prefix . 'threads t ON (t.tid=p.tid)',
            'p.pid',
            "p.pid IN ('{$postIDs}') AND {$whereClauses}"
        );

        $postIDs = [];

        while ($postID = $db->fetch_field($dbQuery, 'pid')) {
            $postIDs[] = (int)$postID;
        }

        $searcharray['posts'] = implode(',', array_filter($postIDs));
    }

    if (!empty($searcharray['querycache'])) {
        $searcharray['querycache'] .= ' AND ';

        $searcharray['querycache'] .= $db->escape_string($whereClauses);
    }
}

function search_results_start(): void
{
    /**
     * @var array $search
     */
    global $search;

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
}

function search_results_thread(&$postData = null): void
{
    global $bgcolor, $inline_mod_checkbox, $lang;

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
        return;
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
}

function search_results_post(): void
{
    global $post;

    search_results_thread($post);
}

function forumdisplay_thread(): void
{
    search_results_thread();
}