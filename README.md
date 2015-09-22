# Emma Dashboard API

A backend API used by Emma Dashboard.

# Installation

1. Install Composer and run: php composer.phar Install
  - Please note that local installation of composer is assumed
2. Create config.php file using config.php.example as a base.
  - Please note that there is a need to provide suitable configurations yourself
  - Please note the EDB_APP_PATH, just leave it empty in case of root installation,
  otherwise it should match RewriteBase, just the ending slash needs to be removed

# API

* course/ID/participants - responds with participants data
* course/ID/activity_stream - responds with stream data
* course/ID/overview - responds with overview Data

The API is currently tailored to suit the needs of certain parts of the UI
