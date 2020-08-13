# CiviCRM-BBPress-Groups
CiviCRM - BBPress integration plugin

## Description 

### Sync CiviCRM groups with WP user accounts, inc. WP roles and BBPress roles

- For any CiviCRM group, specify a WP role and a BBPress role: For each Contact in that group, the syncing process will:
    - Create a WP user account if one doesn't already exist
    - Assign the specified WP role and BBPress role
- Optionally remove roles, when contacts are removed from groups
- Run the syncing process automatically once per day, or manually at any time

### Restrict BBPress forums to specified WP roles

If a forum is restricted, only users who have the specified role(s) can view or participate in it

### Handy shortocdes: Login, logout, My Account and My Profile

- Login shortcode: Output a login form, if the current user is not logged in
- Logout shortcode: Output a logout link or button, if the current user is logged in
- Login-logout shortcode: Output either a login form or logout link/button, depending on whether or not the current user is logged in
- My Account shortcode: Output a My Account form, allowing the current user to view their username and view/update their email address, display name and password. Email address updates are also synced with the corresponding Contact record in CiviCRM.
- My Profile shortcode: Output a My Profile form. Three different My Profile forms can be specified, and the one output will depend on the current user's CiviCRM contact type (Individual, Organisation, or Household). CiviCRM profile forms can be used, or others such as Caldera forms. 

## Installation

1. Upload the plugin to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to `CiviCRM BBPress Groups` in the WP admin left side menu, and configure the plugin to suit your site

## Changelog

### 1.0.3
- Added "Instructions" and "T&Cs" page settings, with links to these from forums
- More Bootstrap styling of forums forms

### 1.0.2
- Removed login_form action from login form on forums 
- Added some padding at bottom of My Account form
- Don't display links in topic tag loop if current user isn't allow to acces the links
- Updated front end user account update process to ensure that a valid nonce can still be set after a password change

### 1.0.1
- Corrected a missing "php" 

### 1.0 
- First release.