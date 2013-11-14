=== Email Post Changes ===
Contributors: mdawaffe, automattic, viper007bond, nickmomrik, dllh, iandunn
Tags: email, diff, post, page, change
Requires at least: 3.2
Tested up to: 3.7.1
Stable tag: 1.7

Emails you whenever a change to a post or page is made.

== Description ==

Each time a change is made to a post or page, those changes are emailed to the users and email addresses you specify.

Optionally, you can set what types of changes to email (post changes, page changes, attachment changes, or changes
to any other 'post type' defined by any other plugin).

The changes are emailed as a unified diff.  If the email client supports HTML emails, the diff will be colorized.


== Installation ==

After uploading and activating the plugin, go to Settings -> Email Post Changes to set up the plugin.

You can change what email addresses to use and for what post types you want change notifications.


== Changelog ==

= 1.7 = 
* Send e-mails to each recipient individually, so that the recipients won't see who else recieved the message.
* Added the `email_post_changes_admin_email_fallback` filter.
* Fixed a PHP notice about $blog_id being undefined.

= 1.6 =
* Fix a few php notices.

= 1.5 =
* Fix bug that caused email to be sent on autosaves when the option to email draft changes was selected.

= 1.4 =
* Fix bug that showed the post owner rather than the current user as having updated a post.

= 1.3 =
* Update for WordPress 3.6 compatibility.

= 1.2 =
* Fix bug when bulk editing posts.

= 1.0 =
* Fix bug preventing changes to 'Users to Email' checkboxes

= 0.9 =
* Allow emails field to be empty if there is at least one user selected.

= 0.8 =
* Add user selection to the Settings page.
* Change the HTML diff so that it only includes 2 leading and trailing lines.
* Fix bug where an invalid email address would keep throwing an error when loading the Settings page.

= 0.7 =
* Fix PHP Warning.
* Remove code that requires PHP 5.2.
* Fix settings link.
* Better post_type labeling.

= 0.6 =
* Pull class out to own file to make it easy to write plugins that extend this functionality.
* Switch to TO instead of BCC.
* Better default options handling.
* Changes to drafts are now ignored by default. New checkbox on settings page to re-enable.

= 0.5 =
* Fix htmlencoding in email subjects.

= 0.4 =
* Fix some PHP Warnings.
* Fix bug when emails array is already an array.
* Remove code that requires PHP 5.3.
* Fix dates in HTML email.
* Fix name of who edited the post.

= 0.3 =
* Fix a Fatal PHP Error.
* Configuration settings: email addresses, post types.


== Upgrade Notice ==

= 1.7 =
* Send e-mails to each recipient individually, so that the recipients won't see who else recieved the message.

= 1.0 =
Bug fixes.

= 0.6 =
Ignore changes to drafts by default.  Send emails with TO instead of BCC.

= 0.5 =
Bug fixes.

= 0.4 =
Reduces PHP dependency to PHP version 5.2.  Bug fixes.

= 0.3 =
Bug fixes.  Configuration settings.
