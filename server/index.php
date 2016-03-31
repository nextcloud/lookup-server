<?php

/**
* Lookup Server main page.
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
* You should have received a copy of the GNU Lesser General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

//makes it easier to debug
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

//set the default timezone to use.
date_default_timezone_set('Europe/Berlin');

//you have to include lib_lookup.
require('lib/lib_lookup.php');

//Do the any brute force check
$bf = new LookupServer_BruteForce();
$bf -> check();

//process the request.
$s = new LookupServer();
$s -> handlerequest();
