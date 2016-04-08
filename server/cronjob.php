#!/usr/bin/php
<?php

/**
* Cronjob. Please call this script every 10min
*
* @author Frank Karlitschek
* @copyright 2016 Frank Karlitschek frank@karlitschek.de
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

// enable the full error reporting for easier debugging
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE | E_ALL);

// include the main contribook lib
require('lib_lookup.php');


// Cronjob
$s = new LookupServer();

// Cleanup the API Traffic Limit
$s -> cleanup();

// Import from other replication hosts
$s -> importReplication();
