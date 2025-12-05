<p align="center">
    <a href="" rel="noopener">
        <img width="700" height="400" src="https://github.com/OUGC-Network/OUGC-Private-Threads/assets/1786584/1c6984ee-f0a2-4f82-9aa4-3e1eef6a859e" alt="Project logo">
    </a>
</p>

<h3 align="center">ougc Private Thread</h3>

<div align="center">

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![GitHub Issues](https://img.shields.io/github/issues/OUGC-Network/OUGC-Private-Threads.svg)](./issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/OUGC-Network/OUGC-Private-Threads.svg)](./pulls)
[![License](https://img.shields.io/badge/license-GPL-blue)](/LICENSE)

</div>

---

<p align="center"> Allow users to mark individual threads as private to be visible for specific users only.
    <br> 
</p>

## 📜 Table of Contents <a name = "table_of_contents"></a>

- [About](#about)
- [Getting Started](#getting_started)
    - [Dependencies](#dependencies)
    - [File Structure](#file_structure)
    - [Install](#install)
    - [Update](#update)
    - [Template Modifications](#template_modifications)
- [Settings](#settings)
    - [File Level Settings](#file_level_settings)
- [Templates](#templates)
- [Usage](#usage)
- [Built Using](#built_using)
- [Authors](#authors)
- [Acknowledgments](#acknowledgement)
- [Support & Feedback](#support)

## 🚀 About <a name = "about"></a>

Private Threads is the go-to MyBB plugin for keeping discussions secret! With this slick tool, you can mark threads
as private, so only specific users get access, keeping things confidential and exclusive. Plus, groups and moderators
can easily bypass the private tag to join in, and users can search for private threads hassle-free. Automatic
notifications let users know when they've been added to a private thread.

[Go up to Table of Contents](#table_of_contents)

## 📍 Getting Started <a name = "getting_started"></a>

The following information will assist you into getting a copy of this plugin up and running on your forum.

### Dependencies <a name = "dependencies"></a>

A setup that meets the following requirements is necessary to use this plugin.

- [MyBB](https://mybb.com/) >= 1.8
- PHP >= 7
- [MyBB-PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) >= 13

### File structure <a name = "file_structure"></a>

  ```
   .
   ├── inc
   │ ├── languages
   │ │ ├── english
   │ │ │ ├── admin
   │ │ │ │ ├── ougcPrivateThreads.lang.php
   │ │ │ ├── ougcPrivateThreads.lang.php
   │ ├── plugins
   │ │ ├── ougc
   │ │ │ ├── PrivateThreads
   │ │ │ │ ├── hooks
   │ │ │ │ │ ├── admin.php
   │ │ │ │ │ ├── forum.php
   │ │ │ │ ├── myalerts
   │ │ │ │ │ ├── thread
   │ │ │ │ │ │ ├── init.php
   │ │ │ │ ├── templates
   │ │ │ │ │ ├── form.html
   │ │ │ │ │ ├── prefix.html
   │ │ │ │ │ ├── search.html
   │ │ │ │ │ ├── showThreadUserList.html
   │ │ │ │ │ ├── showThreadUserListItem.html
   │ │ │ │ ├── settings.json
   │ │ │ │ ├── admin.php
   │ │ │ │ ├── core.php
   │ │ │ │ ├── myalerts.php
   │ │ ├── ougcPrivateThreads.php
   ```

### Installing <a name = "install"></a>

Follow the next steps in order to install a copy of this plugin on your forum.

1. Download the latest package from the [MyBB Extend](https://community.mybb.com/mods.php?action=view&pid=1580) site or
   from the [repository releases](https://github.com/OUGC-Network/OUGC-Landing-Page/releases/latest).
2. Upload the contents of the _Upload_ folder to your MyBB root directory.
3. Browse to _Configuration » Plugins_ and install this plugin by clicking _Install & Activate_.

### Updating <a name = "update"></a>

Follow the next steps in order to update your copy of this plugin.

1. Browse to _Configuration » Plugins_ and deactivate this plugin by clicking _Deactivate_.
2. Follow step 1 and 2 from the [Install](#install) section.
3. Browse to _Configuration » Plugins_ and activate this plugin by clicking _Activate_.

### Template Modifications <a name = "template_modifications"></a>

To display the page link it is required that you edit the following template for each of your themes.

1. Open the `newthread` template for editing.
2. Add `{$ougcPrivateThreadsRow}` after `{$pollbox}`.
3. Open the `editpost` template for editing.
4. Add `{$ougcPrivateThreadsRow}` after `{$pollbox}`.
5. Open the `showthread` template for editing.
6. Add `{$ougcPrivateThreadsAllowedUsersList}` after `{$usersbrowsing}`.
7. Open the `search` template for editing.
8. Add `{$ougcPrivateThreadsSearch}` after `{$lang->show_results_posts}`.
9. Open the `search_results_threads_thread` template for editing.
10. Add `{$thread['ougcPrivateThreadsThreadPrefix']}` after `{$thread['threadprefix']}`.
11. Open the `search_results_posts_post` template for editing.
12. Add `{$post['ougcPrivateThreadsThreadPrefix']}` after `{$lang->post_thread}`.
13. Open the `forumdisplay_thread` template for editing.
14. Add `{$thread['ougcPrivateThreadsThreadPrefix']}` after `{$prefix}`.
15. Open the `forumdisplay_thread_modbit` template for editing.
16. Add `{$thread['ougcPrivateThreadsThreadClass']}` after `{$bgcolor}`.
17. Open the `forumdisplay_thread` template for editing.
18. Add `{$thread['ougcPrivateThreadsThreadClass']}` after `{$bgcolor}`.

[Go up to Table of Contents](#table_of_contents)

## 🛠 Settings <a name = "settings"></a>

Below you can find a description of the plugin settings.

### Global Settings

- **Allowed Groups To View** `select`
    - _Select which groups are allowed to mark their threads as private._
- **Enabled Forums** `select`
    - _Select on which forums to enable this feature._
- **Allow Empty List** `yesNo`
    - _Allow users to mark threads as private without selecting allowed users._
- **Delete Subscriptions on Update** `yesNo`
    - _Delete thread subscriptions when users update the thread user list. If you turn this off users will get
      notifications of existing subscriptions after they are removed from the allowed list._
- **Allowed Groups to Bypass** `select`
    - _Select which groups are allowed to view any private threads._
- **Allow Moderator Bypass** `yesNo`
    - _Allow moderators to view private threads found within the forums they moderate._
- **Allowed Groups to Search** `radio`
    - _Select which groups are allowed to search private threads using the search system._
- **Mark Private Threads** `yesNo`
    - _Display a thread prefix within thread listing to highlight private threads._
- **Prefix Custom Class Name (CSS)** `yesNo`
    - _Select a custom class name to attach to the private thread prefix._
- **Show Users List** `yesNo`
    - _Build a list of allowed users at the bottom of the page for private threads._
- **Allow Private Status Changes** `yesNo`
    - _Allow users to change the private status of existing threads._
- **Notification Methods** `checkBox`
    - _Select the notification method to use when users are added to a private thread._
- **Fix Forum Last Post** `yesNo`
    - _Attempt to fix the forum last post information in the forum index, forum display, and forum subscription pages._
- **Fix Forum Posts & Threads Count** `yesNo`
    - _Attempt to fix the forum threads and posts counter in the forum index, forum display, and forum subscription
      pages. This is highly inaccurate._

### File Level Settings <a name = "file_level_settings"></a>

Additionally, you can force your settings by updating the `SETTINGS` array constant in the `ougc\PrivateThreads\Core`
namespace in the `./inc/plugins/ougcPrivateThreads.php` file. Any setting set this way will always bypass any front-end
configuration. Use the setting key as shown below:

```PHP
define('ougc\PrivateThreads\Core\SETTINGS', [
    'allowedGroups' => '2,3,4,6',
]);
```

[Go up to Table of Contents](#table_of_contents)

## 📐 Templates <a name = "templates"></a>

The following is a list of templates available for this plugin. Uncommon in plugins, we use some templates exclusively
for the _Administrator Control Panel_.

- `ougcPrivateThreads_form`
    - _front end_; used to render the new thread and edit post pages input code
- `ougcPrivateThreads_prefix`
    - _front end_; used to render the private thread prefix code
- `ougcPrivateThreads_search`
    - _front end_; used to render the search page input field
- `ougcPrivateThreads_showThreadUserList`
    - _front end_; used to render the show thread allowed user list
- `ougcPrivateThreads_showThreadUserListItem`
    - _front end_; used to render the show thread allowed user list item for each user

[Go up to Table of Contents](#table_of_contents)

## 📖 Usage <a name="usage"></a>

The following is a description of the _Administrator Control Panel_ module form fields.

[Go up to Table of Contents](#table_of_contents)

## ⛏ Built Using <a name = "built_using"></a>

- [MyBB](https://mybb.com/) - Web Framework
- [MyBB PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) - A collection of useful functions for MyBB
- [PHP](https://www.php.net/) - Server Environment

[Go up to Table of Contents](#table_of_contents)

## ✍️ Authors <a name = "authors"></a>

- [@Omar G](https://github.com/Sama34) - Idea & Initial work

[Go up to Table of Contents](#table_of_contents)

## 🎉 Acknowledgements <a name = "acknowledgement"></a>

- [The Documentation Compendium](https://github.com/kylelobo/The-Documentation-Compendium)

[Go up to Table of Contents](#table_of_contents)

## 🎈 Support & Feedback <a name="support"></a>

This is free development and any contribution is welcome. Get support or leave feedback at the
official [MyBB Community](https://community.mybb.com/thread-159249.html).

Thanks for downloading and using our plugins!

[Go up to Table of Contents](#table_of_contents)