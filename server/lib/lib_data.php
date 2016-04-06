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
 * The main class of the Lookup Server
 */
class LookupServer_Data {

	/**
	 * Get an user data entry
	 * @param string $key
	 * @return array $data
	 */
	public function getByKey($key) {
		$util = new LookupServer_Util();
		$stmt=LookupUpServer_DB::prepare('select userid,federationid,name,email,organisation,country,city,picture,vcard from user where authkey = :key');
		$stmt->bindParam(':key', $key, PDO::PARAM_STR);
		$stmt->execute();
		$num=$stmt->rowCount();

		if($num==0) return(false);
		if($num>1) $util->error('more then one DB entry found for key: '.$key);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return($data);
	}


	/**
	 * Get an user data entry by email
	 * @param string $email
	 * @return array $data
	 */
	public function getByEmail($email) {
		$util = new LookupServer_Util();
		$stmt=LookupUpServer_DB::prepare('select userid,federationid,name,email,organisation,country,city,picture,vcard from user where email=:email and karma>0');
		$stmt->bindParam(':email', $email, PDO::PARAM_STR);
		$stmt->execute();
		$num=$stmt->rowCount();
		if($num==0) return(false);

		if($num>1) $util->error('more then one DB entry found for email: '.$email);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return($data);
	}

	/**
	 * Get an user data entry by userid
	 * @param string $userid
	 * @return array $data
	 */
	public function getByUserId($userid) {
		$util = new LookupServer_Util();
		$stmt=LookupUpServer_DB::prepare('select userid,federationid,name,email,organisation,country,city,picture,vcard from user where userid=:userid and karma>0');
		$stmt->bindParam(':userid', $userid, PDO::PARAM_STR);
		$stmt->execute();
		$num=$stmt->rowCount();
		if($num==0) return(false);

		if($num>1) $util->error('more then one DB entry found for userid: '.$userid);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);
		return($data);
	}

	/**
	 * Check if user exists
	 * @param string $key
	 * @return bool $exists
	 */
	public function userExist($key) {
		$stmt=LookupUpServer_DB::prepare('select id from user where authkey = :key');
		$stmt->bindParam(':key', $key, PDO::PARAM_STR);
		$stmt->execute();
		$num=$stmt->rowCount();

		if($num==1) {
			return(true);
		} else {
			return(false);
		}
	}


	/**
	 * Search users
	 * @param string $search
	 * @param string $start
	 * @param string $count
	 * @return array $data
	 */
	public function searchuser($search,$start,$count) {
		$searchstr = ''.$search.'';
		$stmt=LookupUpServer_DB::prepare("select userid,federationid,name,email,organisation,country,city,picture,vcard from user where match (name,email,organisation,country,city) against (:search in boolean mode) and karma>0 limit :start,:count");
		$stmt->bindParam(':search', $searchstr, PDO::PARAM_STR);
		$stmt->bindParam(':start', $start, PDO::PARAM_INT);
		$stmt->bindParam(':count', $count, PDO::PARAM_INT);
		$stmt->execute();
		$num=$stmt->rowCount();

		$content=array();
		for($i = 0; $i < $num; $i++) {
			$content[]=$stmt->fetch(PDO::FETCH_ASSOC);
		}
		return($content);
	}

	/**
	 * GetReplicationLog
	 * @param int $timestamp
	 * @param int $start
	 * @param int $count
	 * @param bool $fullfetch Get all entries not only the local modified ones
	 * @param bool $slave Don't read the authkey. Useful for replication for not trusted read only nodes
	 * @return array $data
	 */
	public function getReplicationLog($timestamp,$start,$count,$fullfetch,$slave) {
		if(!$fullfetch) $fullquery = 'localchange=1 and'; else $fullquery = '';
		if(!$slave) $authquery = ',authkey'; else $authquery = '';
		$query = "select userid".$authquery.",federationid,name,email,organisation,country,city,picture,vcard,karma,changed,created from user where ".$fullquery." changed >= :timestamp limit :start,:count";
		$stmt=LookupUpServer_DB::prepare($query);
		$stmt->bindParam(':timestamp', $timestamp, PDO::PARAM_STR);
		$stmt->bindParam(':start', $start, PDO::PARAM_INT);
		$stmt->bindParam(':count', $count, PDO::PARAM_INT);
		$stmt->execute();
		$num=$stmt->rowCount();

		$content=array();
		for($i = 0; $i < $num; $i++) {
			$content[]=$stmt->fetch(PDO::FETCH_ASSOC);
		}
		return($content);
	}



	/**
	 * Create a user
	 * @param string $key
	 * @param string $federationid
	 * @param string $name
	 * @param string $email
	 * @param string $organisation
	 * @param string $country
 	 * @param string $city
 	 * @param string $picture
	 * @param string vcard
	 */
	public function store($key,$federationid,$name,$email,$organisation,$country,$city,$picture,$vcard) {
        $util = new LookupServer_Util();
		$userid = $util -> generateUserId();
		$created = time();
		$changed = time();
		$stmt = LookupUpServer_DB::prepare('insert into user (userid,authkey,federationid,name,email,organisation,country,city,picture,vcard,created,changed,localchange) values(:userid,:authkey,:federationid,:name,:email,:organisation,:country,:city,:picture,:vcard,:created,:changed,1)');
		$stmt->bindParam(':userid', $userid, PDO::PARAM_STR);
		$stmt->bindParam(':authkey', $key, PDO::PARAM_STR);
		$stmt->bindParam(':federationid', $federationid, PDO::PARAM_STR);
		$stmt->bindParam(':name', $name, PDO::PARAM_STR);
		$stmt->bindParam(':email', $email, PDO::PARAM_STR);
		$stmt->bindParam(':organisation', $organisation, PDO::PARAM_STR);
		$stmt->bindParam(':country', $country, PDO::PARAM_STR);
		$stmt->bindParam(':city', $city, PDO::PARAM_STR);
		$stmt->bindParam(':picture', $picture, PDO::PARAM_STR);
		$stmt->bindParam(':vcard', $vcard, PDO::PARAM_STR);
		$stmt->bindParam(':created', $created, PDO::PARAM_INT);
		$stmt->bindParam(':changed', $changed, PDO::PARAM_INT);
		$stmt->execute();
	}

	/**
	 * Update user
	 * @param string $key
	 * @param string $federationid
	 * @param string $name
	 * @param string $email
	 * @param string $organisation
	 * @param string $country
	 * @param string $city
	 * @param string $picture
	 * @param string $vcard
	 */
	public function update($key,$federationid,$name,$email,$organisation,$country,$city,$picture,$vcard) {
		$changed = time();
		$stmt=LookupUpServer_DB::prepare('update user set federationid=:federationid,name=:name,email=:email,organisation=:organisation,country=:country,city=:city,picture=:picture,vcard=:vcard,changed=:changed,localchange=1 where authkey=:authkey');
		$stmt->bindParam(':authkey', $key, PDO::PARAM_STR);
		$stmt->bindParam(':federationid', $federationid, PDO::PARAM_STR);
		$stmt->bindParam(':name', $name, PDO::PARAM_STR);
		$stmt->bindParam(':email', $email, PDO::PARAM_STR);
		$stmt->bindParam(':organisation', $organisation, PDO::PARAM_STR);
		$stmt->bindParam(':country', $country, PDO::PARAM_STR);
		$stmt->bindParam(':city', $city, PDO::PARAM_STR);
		$stmt->bindParam(':picture', $picture, PDO::PARAM_STR);
		$stmt->bindParam(':vcard', $vcard, PDO::PARAM_STR);
		$stmt->bindParam(':changed', $changed, PDO::PARAM_INT);
		$stmt->execute();
	}

	/**
	 * Delete an user data entry
	 * @param string $key
	 */
	public function deleteByKey($key) {
		$changed = time();
		$stmt=LookupUpServer_DB::prepare("update user set federationid='',name='',email='',organisation='',country='',city='',picture='',vcard='',changed=:changed,localchange=1,karma=-1,changed=:changed where authkey = :key");
		$stmt->bindParam(':changed', $changed, PDO::PARAM_INT);
		$stmt->bindParam(':key', $key, PDO::PARAM_STR);
		$stmt->execute();
	}

}
