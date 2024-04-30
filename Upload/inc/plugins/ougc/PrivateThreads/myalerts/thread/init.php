<?php

use function ougc\PrivateThreads\Core\loadLanguage;

/***************************************************************************
 *
 *    OUGC Private Threads plugin (/inc/plugins/ougc/PrivateThreads/myalerts/thread/init.php)
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

class MybbStuff_MyAlerts_Formatter_ougcPrivateThreadsThreadFormatter extends
    MybbStuff_MyAlerts_Formatter_AbstractFormatter
{
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert): string
    {
        global $parser, $lang;

        $tid = $alert->getObjectId();

        $threadData = get_thread($tid);

        if (!($parser instanceof postParser)) {
            require_once MYBB_ROOT . 'inc/class_parser.php';

            $parser = new postParser();
        }

        return $lang->sprintf(
            $lang->myalerts_ougcPrivateThreadsThread,
            $outputAlert['from_user'],
            htmlspecialchars_uni($parser->parse_badwords($threadData['subject'])),
            $outputAlert['dateline']
        );
    }

    public function init(): bool
    {
        loadLanguage();

        return true;
    }

    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert): string
    {
        global $mybb;

        return $mybb->settings['bburl'] . '/' . get_thread_link($alert->getObjectId());
    }
}