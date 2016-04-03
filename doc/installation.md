# Installation

## System Requirements
* Linux Server
* Apache 2.4
* PHP 5.6 or newer
* MySQL

## Installation
* Setup an Apache SSL/TLS vhost
* Put the content of the server subfolder in the vhost root.
* Create a lookup MySQL database. User the mysql.dmp for that.
* Create a config.php and adapt the settings. config.sample.php can be used as a template.
* Make sure that the config folder is not accessible from the internet by configure Apache to respect the .htaccess
* Add a cronjob that calls cronjob.php every few minutes. Recommended is 10min
* Configure an automatic backup of the mysql database and the vhost folder.
* Test the installation by executing the example curl commands listed in the architecture.md file.



## Operations
* It is recommended to activate the logfile and monitor the activity at the beginning to make sure everything works
* There is an additional replication logfile. This should also be monitored to make sure everything works
* Regular Backups are strongly recommended
* github.com/owncloud/lookup-server should be checked regulary to make sure that all securit updates are installed
