# Emma Dashboard API

A backend API used by Emma Dashboard.

# Requirements

* PHP version 5.3.x or later
* JSON extension
* Mongo Client (latest version; tested with 1.6.11)
* Apache with mod_rewrite

# Installation

1. Install Composer and run: php composer.phar Install
  - Please note that local installation of composer is assumed
2. Create config.php file using config.php.example as a base.
  - Please note that there is a need to provide suitable configurations yourself
  - Please note the EDB_APP_PATH, just leave it empty in case of root installation,
  otherwise it should match RewriteBase, just the ending slash needs to be removed
3. Make sure that **data/** catalog is writable for the apache

# API

* version - responds with current version information (not API versioning, just the version of the app)
* course/ID/participants - responds with participants data
* course/ID/activity_stream - responds with stream data
* course/ID/overview - responds with overview data
* course/ID/lessons - responds with lessons and units data (just structural)
* course/ID/lesson/ID/unit/ID - responds with unit data
* course/ID/sna - responds with network data (Sigma.js friendly)

The API is currently tailored to suit the needs of certain parts of the UI

Every response should also have a header **edb-app-version** with version information.
Please do not mistaken that with API versioning, there is only one API and this one refers to the APP version.
At the moment there is no intention on creating multiple API versions, if that ever happens, then it would use
a better strategy of prefixing the URI like **v1/course/ID/participants**.

# Upgrade

## 1.2.0 to 1.3.0

No new configurations added, contents are compatible and config files are reusable

* Download new package and extract the contents
* Reuse config.php and .htaccess files from the previous installation
* You can either load modules with Composer or just reuse the previous **vendor** catalog (its contents)
* Make sure the **data** catalog is writable for the user running Apache httpd server

## 1.3.0 to 1.4.0

Added **EDB_ENABLE_PROTECTION** to config.php file. Default value is **true**.
That could be disabled in case of development environment or if there is a need
to test something without logging in as different users.

* Download new package and extract the contents
* Reuse .htaccess file from the previous installation
* config.php could also be reused, provided definition for **EDB_ENABLE_PROTECTION** is added from **config.php.example** file
* You can either load modules with Composer or just reuse the previous **vendor** catalog (its contents)
* Make sure the **data** catalog is writable for the user running Apache httpd server
