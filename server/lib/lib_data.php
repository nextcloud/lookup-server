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
		$stmt=LookupUpServer_DB::prepare('select id,federationid,name,email,country,city,picture,vcard from user where authkey = :key');
        $stmt->bindParam(':key', $key, PDO::PARAM_STR);
        $stmt->execute();
        $num=$stmt->rowCount();

		if($num==0) return(array());
		if($num>1) $this->error('more then one DB entry found for key: '.$key);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
		return($data);
	}


	/**
	 * Search users
	 * @param string $search
	 * @param string $start
	 * @param string $count
	 * @return array $data
	 */
	public function searchuser($search,$start,$count) {
		$searchstr = '%'.$search.'%';
		$stmt=LookupUpServer_DB::prepare('select id,federationid,name,email,country,city,picture,vcard from user where name like :search limit :start,:count');
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
	 * Create a user
	 * @param string $key
	 * @param string $federationid
	 * @param string $name
	 * @param string $email
	 * @param string $country
 	 * @param string $city
 	 * @param string $picture
	 * @param string vcard
	 */
	public function store($key,$federationid,$name,$email,$country,$city,$picture,$vcard) {
		$stmt = LookupUpServer_DB::prepare('insert into user (authkey,federationid,name,email,country,city,picture,vcard) values(:authkey,:federationid,:name,:email,:country,:city,:picture,:vcard)');
		$stmt->bindParam(':authkey', $key, PDO::PARAM_STR);
		$stmt->bindParam(':federationid', $federationid, PDO::PARAM_STR);
		$stmt->bindParam(':name', $name, PDO::PARAM_STR);
		$stmt->bindParam(':email', $email, PDO::PARAM_STR);
		$stmt->bindParam(':country', $country, PDO::PARAM_STR);
		$stmt->bindParam(':city', $city, PDO::PARAM_STR);
		$stmt->bindParam(':picture', $picture, PDO::PARAM_STR);
		$stmt->bindParam(':vcard', $vcard, PDO::PARAM_STR);
		$stmt->execute();
	}

	/**
	 * Update user
	 * @param string $key
	 * @param string $federationid
	 * @param string $name
	 * @param string $email
	 * @param string $country
	 * @param string $city
	 * @param string $picture
	 * @param string $vcard
	 */
	public function update($key,$federationid,$name,$email,$country,$city,$picture,$vcard) {
		$stmt=LookupUpServer_DB::prepare('update user set federationid=:federationid,name=:name,email=:email,country=:country,city=:city,picture=:picture,vcard=:vcard where authkey=:authkey');
		$stmt->bindParam(':authkey', $key, PDO::PARAM_STR);
		$stmt->bindParam(':federationid', $federationid, PDO::PARAM_STR);
		$stmt->bindParam(':name', $name, PDO::PARAM_STR);
		$stmt->bindParam(':email', $email, PDO::PARAM_STR);
		$stmt->bindParam(':country', $country, PDO::PARAM_STR);
		$stmt->bindParam(':city', $city, PDO::PARAM_STR);
		$stmt->bindParam(':picture', $picture, PDO::PARAM_STR);
		$stmt->bindParam(':vcard', $vcard, PDO::PARAM_STR);
		$stmt->execute();
	}

	/**
	 * Delete an user data entry
	 * @param string $key
	 */
	public function deleteByKey($key) {
		$stmt=LookupUpServer_DB::prepare('delete from user where authkey = :key');
        $stmt->bindParam(':key', $key, PDO::PARAM_STR);
        $stmt->execute();
	}


	/**
	 *  Handle error
	 * @param string $text
	 */
	public function error($text) {
		error_log($text);
		exit;
	}


}
