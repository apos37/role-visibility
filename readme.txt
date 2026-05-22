=== Role Visibility ===
Contributors: apos37
Tags: access, block, restrict, permission, user
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.1
License: Proprietary
License URI: https://pluginrx.com/proprietary-license-agreement/

Manage visibility of posts and pages by role or guest

== Description ==
**Role Visibility** is a premium WordPress plugin that allows you to control who can view your posts and pages on the front-end. With this plugin, you can specify access levels based on user roles, enhancing your content visibility management.

**Key Features:**

- **Access Control:** Choose from default settings including "Everyone", "Logged-In Only", and "Logged-Out Only".
- **Role Selection:** When selecting "Logged-In Only", you can specify which user roles are allowed to view the content.
- **Custom Messages:** Customize the message displayed on a per post/page level or globally based on your settings.
- **User-Friendly Interface:** Seamlessly integrates into your WordPress editor for easy access.

== Installation ==
1. Upload the `role-visibility` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the settings to configure your default visibility options.
4. Edit your posts/pages to set specific visibility settings as needed.

== Frequently Asked Questions ==
= Can I customize the visibility message? =
Yes, you can customize the visibility message on both a per post/page level and globally.

= Can I choose specific user roles for "Logged-In Only"? =
Absolutely! When you select "Logged-In Only", you will have the option to specify which roles can view the content.

== Screenshots ==
1. Admin column in Pages list table
2. Quick edit on Pages list table (bulk edit also works)
3. Individual page settings
4. Front-end displaying logged-in template and default message for Page post type
5. Settings

== Changelog ==
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