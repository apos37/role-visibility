=== Role Visibility — Hide Pages & Posts Based on Roles ===
Contributors: apos37
Tags: restrict content, access control, user roles, members only, hide pages
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Control who can view your posts and pages by role, specific users or login status, with customizable access denied templates.

== Description ==

**Role Visibility** lets you decide who can view each post, page or custom post type on the front end. Visitors who cannot view something are sent to your No Access Page, which shows a template explaining why, with links to log in, register or try again.

= Features =

* **Access per post:** Everyone, Logged-In Only or Logged-Out Only, from a box on the edit screen, Quick Edit or Bulk Edit.
* **Limit by role or user:** narrow "Logged-In Only" content to specific roles, specific users, or both.
* **Post type defaults:** choose which post types have access controls and the access their content uses by default.
* **Templates:** access denied messages are templates you edit in the block editor or Classic Editor. Make them global, for certain post types, or for specific posts, and choose when each one applies.
* **Merge tags:** drop in log in, log out, register, retry and homepage links or URLs, the page title, the required roles and more.
* **Hide restricted content:** optionally remove restricted content from archives, search results, RSS feeds, the REST API, XML sitemaps, navigation menus and previous/next links.
* **Navigation menus:** show or hide menu items by login status or role in Appearance > Menus and in the Navigation block. Items that link to content can simply match that content's access.
* **Import from Nav Menu Roles:** bring over your existing menu item rules in one click.
* **Administrator bypass:** optionally let administrators view everything.
* **Lightweight:** no extra queries for post meta when hiding content, and scripts and styles only load where they are used.
* **Developer friendly:** actions and filters throughout.

= Getting Started =

1. Go to **Role Visibility > Settings** and select or create your **No Access Page**. It must contain the `[role_visibility_msg]` shortcode.
2. Under **Post Types**, enable the post types you want to control and choose their default access.
3. Edit a post or page and choose who can view it in the **Role Visibility** box.
4. Customize the messages under **Role Visibility > Templates**.

== Installation ==

1. Install the plugin from Plugins > Add New, or upload the `role-visibility` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Go to Role Visibility > Settings to finish setup.

== Frequently Asked Questions ==

= Why can visitors still view restricted pages? =
Visitors are only redirected once a published No Access Page is selected under Role Visibility > Settings. Also check that the post type is enabled under Post Types.

= How do templates work? =
When a visitor is redirected, the No Access Page shows the template that matches the restricted post and the reason the visitor was blocked: they need to log in, they lack the required role or user, or they are logged in on a logged-out-only page. A template for specific posts wins over one for post types, which wins over a global one. If nothing matches, a built-in default is used.

= Can I show a different message on one page? =
Yes. In the Role Visibility box on that page, click "Create a unique template for this post".

= Will hiding restricted content work with my theme and plugins? =
Restricted content is always protected when visited directly. The optional hiding features depend on how your theme and other plugins load content:

* **Archives, search and feeds** cover queries made through WordPress, including Query Loop blocks and most page builder widgets. Content loaded with custom database queries, or with `suppress_filters` turned on, is not affected.
* **Front-end AJAX** requests, such as "load more" buttons, are not filtered by default. Developers can add `'rolevisibility_context' => 'archives'` to the query arguments to include them.
* **XML sitemaps** are supported for WordPress core, Yoast SEO, Rank Math, All in One SEO and SEOPress. Other sitemap generators may still list restricted content. Remember to clear your SEO plugin's sitemap cache after changing access.
* **Navigation menus** work in Appearance > Menus and in the Navigation block. Menus built by page builders or other menu plugins may not be affected. The Customizer's menu editor does not show the visibility controls.
* **Previous/next links** work with WordPress functions and blocks. Themes that build these links with their own queries may still show restricted posts, but those posts are still protected when clicked.

= Is the hiding feature fast on large sites? =
Yes. Access rules are kept in a single cached index, so no post meta joins are added to your queries. For the best performance, restrict whole post types with the default access and allow exceptions per post, rather than restricting thousands of posts one by one.

= Does it work with caching plugins? =
Restricted pages redirect before any content is sent, and the No Access Page tells caching plugins not to cache it. If a page cache serves logged-out visitors, clear it after changing access rules.

= What happens to my data when I delete the plugin? =
Nothing is removed unless you check "Remove All Plugin Data on Uninstall" under Settings > Advanced.

== Screenshots ==

1. Settings
2. Templates
3. Editing a template with merge tags
4. Role Visibility box on the edit screen
5. Role Visibility column and Quick Edit
6. Menu item visibility

== Changelog ==

= 2.0.0 =
* Now free on WordPress.org, renamed "Role Visibility — Hide Pages & Posts Based on Roles"
* New: Access denied templates as a post type, editable in the block editor or Classic Editor, replacing default and per-post messages
* New: Template merge tags and merge tag picker
* New: Limit "Logged-In Only" content to specific users
* New: Optionally hide restricted content from archives, search, feeds, the REST API, XML sitemaps, navigation menus and previous/next links
* New: Menu item visibility for classic menus and the Navigation block, with import from Nav Menu Roles
* New: Administrators Bypass Restrictions option
* New: Download, upload and reset settings
* New: Create a No Access Page from Settings
* New: Admin menu with Settings and Templates tabs
* New: Developer hooks
* Update: Settings save without reloading the page
* Update: Version 1.x settings, access rules and messages are migrated automatically
* Fix: Restricted pages could redirect to the wrong page before settings were saved
* Fix: The access box could disappear on every post before settings were saved
* Fix: Archive and blog pages were redirected when their first post was restricted
* Fix: Users without the required role saw the wrong message
* Fix: Missing Role and Logged-Out Only messages were not used
* Fix: Logged-out visitors on role-restricted pages did not get a login link
* Fix: Role boxes left checked under other access options could leave content unprotected
* Removed: License settings

= 1.1.1 =
* Update: Added {register_link} merge tag
* Fix: Default Message for Logged-In Only not displaying
* Fix: Checkboxes in Quick Edit missing next to role labels
* Compatibility: Tested with WordPress 7.0

= 1.1.0 =
* Update: New support links

= 1.0.2.2 =
* Fix: Duplicate unique ids on wp list table

= 1.0.2.1 =
* Fix: PHP Notice => Function _load_textdomain_just_in_time was called incorrectly.

= 1.0.2 =
* Update: Updated author name and website per WordPress trademark policy

= 1.0.1 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Major update. Settings, access rules and custom messages are migrated automatically, and messages become editable templates. Review Role Visibility > Templates after updating.
