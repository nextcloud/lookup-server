<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Installation

## System Requirements
* Linux Server
* Apache 2.4
* PHP 5.6 or newer
* MySQL

## Installation steps
1. Create a lookup MySQL database. Use the mysql.dmp for that.
1. Setup an Apache SSL/TLS vhost for the lookup server
2. Put the contents of the server subfolder in the vhost root.
4. Create a config.php file and adapt the settings. config.sample.php can be used as a template.
5. Make sure the config folder is not accessible from the internet by configuring Apache to respect the .htaccess
6. Add a cronjob that calls replicationcron.php every few minutes. Recommended is 10min
7. Configure an automatic backup of the mysql database and the vhost folder.
8. Test the installation by executing the example curl commands listed in the architecture.md file.

### Initialization of the lookup server database

On the database server, copy the [database dump file](../mysql.dmp) to initialize the database. Run `mysql -u username -p database_name < mysql.dmp` to import the dump in the database.

### Configuration of the webserver

Once the apache SSL/TLS vhost is ready, copy the content of the [server directory](../server) in the vhost root, e.g. `var/www/html` if using Apache defaults.

### Tune lookup server config.php

The main parameters to configure are:
- `DB` array (host, db, user, pass)
- `PUBLIC_URL` of the lookup server

If in a global scale setup, the following parameters are to set as well:
- `GLOBAL_SCALE` to `true`
- `AUTH_KEY` to a secure value

A full list of the parameters available is accessible [there](../server/config/config.sample.php).

### Configure Apache to respect the `.htaccess`

### Configure cronjob

The job that must run every 10 minutes for the lookup server is `replicationcron.php`, located in the webserver root.
If not familiar with cronjob, please refer to the Nextcloud official documentation [here](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/background_jobs_configuration.html#cron) about how to setup cronjobs.

**NB:*** Note that you will have to replace `cron.php` by `replicationcron.php`.

### Configure the backups

For the configuration folder, instructions are similar to those for the Nextcloud server [here](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/backup.html#backup-folders).

For the database, instructions are similar to those for the Nextcloud server [here](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/backup.html#mysql-mariadb).

## Test the installation




## Operations
* It is recommended to activate the logfile and monitor the activity at the beginning to make sure everything works
* There is an additional replication logfile. This should also be monitored to make sure everything works
* Regular Backups are strongly recommended
* github.com/nextcloud/lookup-server should be checked regulary to make sure all security updates are installed

