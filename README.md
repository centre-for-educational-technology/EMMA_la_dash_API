# Emma Dashboard API

A backend API used by Emma Dashboard.

# Installation

1. Install Composer and run: php composer.phar Install
  - Please note that local installation of composer is assumed
2. Create config.php file using config.php.example as a base.
  - Please note that there is a need to provide suitable configurations yourself
  - Please note the EDB_APP_PATH, just leave it empty in case of root installation,
  otherwise it should match RewriteBase, just the ending slash needs to be removed
3. Make sure that *data/* catalog is writable for the apache

# API

* course/ID/participants - responds with participants data
* course/ID/activity_stream - responds with stream data
* course/ID/overview - responds with overview data
* course/ID/lessons - responds with lessons and units data (just structural)
* course/ID/lesson/ID/unit/ID - responds with unit data
* course/ID/sna - responds with network data (Sigma.js friendly)

The API is currently tailored to suit the needs of certain parts of the UI
