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

// max user search page. limit the maximum number of pages to avoid scraping.
define('LOOKUPSERVER_MAX_SEARCH_PAGE', 10);

// max requests per IP and 10min.
define('LOOKUPSERVER_MAX_REQUESTS', 10000);
