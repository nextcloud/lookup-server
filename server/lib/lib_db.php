<?php

/**
* Lookup Server DB Lib
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

/**
* The LookUp Server database access class
*/
class LookupServer_DB {

    /**
     * prepare a query on the database
     *
     * @param string $cmd
     * @return statement object $stmt
     */
    public static function prepare($cmd) {
        global $LookupServer_Connection;

        if(!isset($LookupServer_Connection)) {
            $LookUpServer_Connection = new PDO(LOOKUPSERVER_DB_STRING, LOOKUPSERVER_DB_LOGIN, LOOKUPSERVER_DB_PASSWD);
			$LookUpServer_Connection -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if (!$LookUpServer_Connection) {
                @ob_end_clean();
                echo('Can not connect to the database. Please check your configuration.');
                exit();
            }
        }
        $stmt = $LookUpServer_Connection->prepare($cmd);
        return($stmt);
    }

}
