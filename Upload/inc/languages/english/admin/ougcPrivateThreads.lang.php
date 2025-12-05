<?php

/***************************************************************************
 *
 *    ougc Private Thread plugin (/inc/languages/english/admin/ougcPrivateThreads.lang.php)
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

$l = [
    'ougcPrivateThreads' => 'ougc Private Thread',
    'ougcPrivateThreadsDescription' => 'Allow users to mark individual threads as private to be visible for specific users only.',
    'ougcPrivateThreadsDescriptionMyAlerts' => '<br /><code><a href="./index.php?module=config-plugins&amp;action=ougcPrivateThreads">Install MyAlerts Integration</a></code>',

    'setting_group_ougcPrivateThreads' => 'Private Threads',
    'setting_group_ougcPrivateThreads_desc' => 'Allow users to mark individual threads as private to be visible for specific users only.',

    'setting_ougcPrivateThreads_allowedGroups' => 'Allowed Groups',
    'setting_ougcPrivateThreads_allowedGroups_desc' => 'Select which groups are allowed to mark their threads as private.',
    'setting_ougcPrivateThreads_enabledForums' => 'Enabled Forums',
    'setting_ougcPrivateThreads_enabledForums_desc' => 'Select on which forums to enable this feature.',
    'setting_ougcPrivateThreads_allowEmptyUserList' => 'Allow Empty List',
    'setting_ougcPrivateThreads_allowEmptyUserList_desc' => 'Allow users to mark threads as private without selecting allowed users.',
    'setting_ougcPrivateThreads_deleteSubscriptions' => 'Delete Subscriptions on Update',
    'setting_ougcPrivateThreads_deleteSubscriptions_desc' => 'Delete thread subscriptions when users update the thread user list. If you turn this off users will get notifications of existing subscriptions after they are removed from the allowed list.',
    'setting_ougcPrivateThreads_allowGroupsBypass' => 'Allowed Groups to Bypass',
    'setting_ougcPrivateThreads_allowGroupsBypass_desc' => 'Select which groups are allowed to view any private threads.',
    'setting_ougcPrivateThreads_allowModeratorBypass' => 'Allow Moderator Bypass',
    'setting_ougcPrivateThreads_allowModeratorBypass_desc' => 'Allow moderators to view private threads found within the forums they moderate.',
    'setting_ougcPrivateThreads_enableSearchSystem' => 'Allowed Groups to Search',
    'setting_ougcPrivateThreads_enableSearchSystem_desc' => 'Select which groups are allowed to search private threads using the search system.',
    'setting_ougcPrivateThreads_assignPrefix' => 'Mark Private Threads',
    'setting_ougcPrivateThreads_assignPrefix_desc' => 'Display a thread prefix within thread listing to highlight private threads.',
    'setting_ougcPrivateThreads_prefixClassName' => 'Prefix Custom Class Name (CSS)',
    'setting_ougcPrivateThreads_prefixClassName_desc' => 'Select a custom class name to attach to the private thread prefix.',
    'setting_ougcPrivateThreads_showUserList' => 'Show Users List',
    'setting_ougcPrivateThreads_showUserList_desc' => 'Build a list of allowed users at the bottom of the page for private threads.',
    'setting_ougcPrivateThreads_allowStatusUpdate' => 'Allow Private Status Changes',
    'setting_ougcPrivateThreads_allowStatusUpdate_desc' => 'Allow users to change the private status of existing threads.',
    'setting_ougcPrivateThreads_notificationTypes' => 'Notification Methods',
    'setting_ougcPrivateThreads_notificationTypes_desc' => 'Select the notification method to use when users are added to a private thread.',
    'setting_ougcPrivateThreads_notificationTypes_myalerts' => 'MyAlerts',
    'setting_ougcPrivateThreads_notificationTypes_pm' => 'Private Message',
    'setting_ougcPrivateThreads_fixForumLastPost' => 'Fix Forum Last Post',
    'setting_ougcPrivateThreads_fixForumLastPost_desc' => 'Attempt to fix the forum last post information in the forum index, forum display, and forum subscription pages.',
    'setting_ougcPrivateThreads_fixForumCount' => 'Fix Forum Posts & Threads Count',
    'setting_ougcPrivateThreads_fixForumCount_desc' => 'Attempt to fix the forum threads and posts counter in the forum index, forum display, and forum subscription pages. This is highly inaccurate.',

    'ougcPrivateThreads_myalerts_confirm' => 'Are you sure you want to install MyAlerts integration?',
    'ougcPrivateThreads_myalerts_success' => 'MyAlerts integration has successfully been installed.',

    'ougcPrivateThreadsPluginLibrary' => 'This plugin requires <a href="{1}">PluginLibrary</a> version {2} or later to be uploaded to your forum.',
];