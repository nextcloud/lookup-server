<?php

// Lookup-Server Config

// DB connect string
define('LOOKUPSERVER_DB_STRING', 'mysql:host=localhost;dbname=lookup' );

// DB login
define('LOOKUPSERVER_DB_LOGIN', '');

// DB password
define('LOOKUPSERVER_DB_PASSWD', '');

// error verbose
define('LOOKUPSERVER_ERROR_VERBOSE', true);

// logfile
define('LOOKUPSERVER_LOG', '/var/log/owncloud/lookup.log');

// replication logfile
define('LOOKUPSERVER_REPLICATION_LOG', '/var/log/owncloud/lookup_replication.log');

// max user search page. limit the maximum number of pages to avoid scraping.
define('LOOKUPSERVER_MAX_SEARCH_PAGE', 10);

// max requests per IP and 10min.
define('LOOKUPSERVER_MAX_REQUESTS', 10000);

// credential to read the replication log. IMPORTANT!! SET TO SOMETHING SECURE!!
define('LOOKUPSERVER_REPLICATION_AUTH', 'foobar');

// credential to read the slave replication log. Replication slaves are read only and don't get the authkey. IMPORTANT!! SET TO SOMETHING SECURE!!
define('LOOKUPSERVER_SLAVEREPLICATION_AUTH', 'slavefoobar');

// the list of remote replication servers that should be queried in the cronjob
$LOOKUPSERVER_REPLICATION_HOSTS = ARRAY ('https://lookup:slavefoobar@example.com');

// replication interval. The number of seconds into the past that should be used when fetching the replication log from a remote server. Should be a bit higher then the cronjob intervall
define('LOOKUPSERVER_REPLICATION_INTERVAL', 900); // 15min

// ip black list. usefull to block spammers.
$LOOKUPSERVER_IP_BLACKLIST = array ('333.444.555.','666.777.888.');

// Email sender address
define('LOOKUPSERVER_EMAIL_SENDER', 'admin@example.com');

// Public Server Url
define('LOOKUPSERVER_PUBLIC_URL', 'http://dev/owncloud/lookup-server'); 
