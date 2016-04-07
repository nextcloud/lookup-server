<?php

/**
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
 * Basic Brute Force Protection Class
 */
class LookupServer_BruteForce {

	/**
	 * Check if there are too many requests from one IP
	 * @return bool $block
	 */
	public function check() {

		// register new ip
		$ip = ip2long($_SERVER['REMOTE_ADDR']);
		$stmt=LookupServer_DB::prepare('insert into apitraffic (ip,count) values (:ip,1) on duplicate key update count=count+1 ');
        $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
        $stmt->execute();

		$stmt=LookupServer_DB::prepare('select count from apitraffic where ip=:ip ');
        $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
	    $stmt->execute();
        $num=$stmt->rowCount();

		if($num==0) return(true);
    	$data = $stmt->fetch(PDO::FETCH_ASSOC);
		if($data['count']>LOOKUPSERVER_MAX_REQUESTS) {
			echo(json_encode(array('error'=>'Too many requests. Please try again later.'),JSON_PRETTY_PRINT));
			exit;
		}

	}


	/**
	* cleans up the api traffic limit database table.
	* this function should be call by a cronjob every 10 minutes
	*/
	public function cleanupTrafficLimit() {
		$stmt=LookupServer_DB::prepare('truncate apitraffic');
		$stmt->execute();
	}


}
