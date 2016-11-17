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

namespace LookupServer;

/**
 * The main class of the Lookup Server
 */
class Data {

	/**
	 * Get an user data entry
	 * @param string $key
	 * @return array $data
	 */
	public function getByKey($key) {
		$util = new Util();
		$stmt = DB::prepare('select userid,federationid,name,email,organisation,country,city,picture,vcard from user where authkey = :key');
		$stmt->bindParam(':key', $key, \PDO::PARAM_STR);
		$stmt->execute();
		$num=$stmt->rowCount();

		if ($num==0) {
            return false;
        }

		if ($num>1) {
            $util->error('more then one DB entry found for key: '.$key);
        }
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
		return($data);
	}


	/**
	 * Get an user data entry by email
	 * @param string $email
	 * @return array $data
	 */
	public function getByEmail($email) {
		$util = new Util();
		$stmt = DB::prepare('select userid,federationid,name,email,organisation,country,city,picture,vcard from user where email=:email and karma>0');
		$stmt->bindParam(':email', $email, \PDO::PARAM_STR);
		$stmt->execute();
		$num=$stmt->rowCount();
		if ($num==0) {
            return false;
        }

		if ($num>1) {
            $util->error('more then one DB entry found for email: '.$email);
        }

		$data = $stmt->fetch(\PDO::FETCH_ASSOC);
		return($data);
	}

	/**
	 * Get an user data entry by userid
	 * @param string $userid
	 * @return array $data
	 */
	public function getByUserId($userid) {
		$util = new Util();
		$stmt = DB::prepare('select userid,federationid,name,email,organisation,country,city,picture,vcard from user where userid=:userid and karma>0');
		$stmt->bindParam(':userid', $userid, \PDO::PARAM_STR);
		$stmt->execute();
		$num=$stmt->rowCount();
		if ($num==0) {
            return false;
        }

		if ($num>1) {
            $util->error('more then one DB entry found for userid: '.$userid);
        }

        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
		return($data);
	}

	/**
	 * Check if user exists
	 * @param string $key
	 * @return bool $exists
	 */
	public function userExist($key) {
		$stmt = DB::prepare('select userid from user where authkey = :key');
		$stmt->bindParam(':key', $key, \PDO::PARAM_STR);
		$stmt->execute();
		$num=$stmt->rowCount();

		if($num>0) {
			return true;
		} else {
			return false;
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
		$stmt = DB::prepare("select userid,federationid,name,email,organisation,country,city,picture,vcard from user where match (name,email,organisation,country,city) against (:search in boolean mode) and karma>0 order by karma desc limit :start,:count");
		$stmt->bindParam(':search', $searchstr, \PDO::PARAM_STR);
		$stmt->bindParam(':start', $start, \PDO::PARAM_INT);
		$stmt->bindParam(':count', $count, \PDO::PARAM_INT);
		$stmt->execute();
		$num=$stmt->rowCount();

		$content=array();
		for($i = 0; $i < $num; $i++) {
			$content[]=$stmt->fetch(\PDO::FETCH_ASSOC);
		}
		return($content);
	}

	/**
	 * exportReplication
	 * @param int $timestamp
	 * @param int $start
	 * @param int $count
	 * @param bool $fullfetch Get all entries not only the local modified ones
	 * @param bool $slave Don't read the authkey. Useful for replication for not trusted read only nodes
	 * @return array $data
	 */
	public function exportReplication($timestamp,$start,$count,$fullfetch,$slave) {
		if(!$fullfetch) $fullquery = 'localchange=1 and'; else $fullquery = '';
		if(!$slave) $authquery = ',authkey'; else $authquery = '';
		$query = "select userid".$authquery.",federationid,name,email,organisation,country,city,picture,vcard,karma,changed,created from user where ".$fullquery." changed >= :timestamp limit :start,:count";
		$stmt = DB::prepare($query);
		$stmt->bindParam(':timestamp', $timestamp, \PDO::PARAM_STR);
		$stmt->bindParam(':start', $start, \PDO::PARAM_INT);
		$stmt->bindParam(':count', $count, \PDO::PARAM_INT);
		$stmt->execute();
		$num=$stmt->rowCount();

		$content=array();
		for($i = 0; $i < $num; $i++) {
			$content[]=$stmt->fetch(\PDO::FETCH_ASSOC);
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
	 * @param string $vcard
	 */
	public function store($key,$federationid,$name,$email,$organisation,$country,$city,$picture,$vcard) {
		$util = new Util();

		// check if email already exists
		if($this->getByEmail($email)) {
			$util->error('Can\'t store user because of duplicate email: '.$email);
		}

		$userid = $util->generateUserId();
		$created = time();
		$changed = time();
		$stmt = DB::prepare('insert into user (userid,authkey,federationid,name,email,organisation,country,city,picture,vcard,created,changed,localchange) values(:userid,:authkey,:federationid,:name,:email,:organisation,:country,:city,:picture,:vcard,:created,:changed,1)');
		$stmt->bindParam(':userid', $userid, \PDO::PARAM_STR);
		$stmt->bindParam(':authkey', $key, \PDO::PARAM_STR);
		$stmt->bindParam(':federationid', $federationid, \PDO::PARAM_STR);
		$stmt->bindParam(':name', $name, \PDO::PARAM_STR);
		$stmt->bindParam(':email', $email, \PDO::PARAM_STR);
		$stmt->bindParam(':organisation', $organisation, \PDO::PARAM_STR);
		$stmt->bindParam(':country', $country, \PDO::PARAM_STR);
		$stmt->bindParam(':city', $city, \PDO::PARAM_STR);
		$stmt->bindParam(':picture', $picture, \PDO::PARAM_STR);
		$stmt->bindParam(':vcard', $vcard, \PDO::PARAM_STR);
		$stmt->bindParam(':created', $created, \PDO::PARAM_INT);
		$stmt->bindParam(':changed', $changed, \PDO::PARAM_INT);
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
		$util = new Util();

		// check if email already exists
		$query = 'select userid from user where email=:email and authkey!=:authkey';
		$stmt = DB::prepare($query);
		$stmt->bindParam(':authkey', $key, \PDO::PARAM_STR);
		$stmt->bindParam(':email', $email, \PDO::PARAM_STR);
		$stmt->execute();
		$num = $stmt->rowCount();
		if ($num>0) {
			$util -> error('ERROR UPDATE USER: Can\'t update user because of duplicate email: '.$email);
		}

		$changed = time();
		$stmt = DB::prepare('update user set federationid=:federationid,name=:name,email=:email,organisation=:organisation,country=:country,city=:city,picture=:picture,vcard=:vcard,changed=:changed,localchange=1 where authkey=:authkey');
		$stmt->bindParam(':authkey', $key, \PDO::PARAM_STR);
		$stmt->bindParam(':federationid', $federationid, \PDO::PARAM_STR);
		$stmt->bindParam(':name', $name, \PDO::PARAM_STR);
		$stmt->bindParam(':email', $email, \PDO::PARAM_STR);
		$stmt->bindParam(':organisation', $organisation, \PDO::PARAM_STR);
		$stmt->bindParam(':country', $country, \PDO::PARAM_STR);
		$stmt->bindParam(':city', $city, \PDO::PARAM_STR);
		$stmt->bindParam(':picture', $picture, \PDO::PARAM_STR);
		$stmt->bindParam(':vcard', $vcard, \PDO::PARAM_STR);
		$stmt->bindParam(':changed', $changed, \PDO::PARAM_INT);
		$stmt->execute();
	}

	/**
	 * Delete an user data entry
	 * @param string $key
	 */
	public function deleteByKey($key) {
		$changed = time();
		$stmt = DB::prepare("update user set federationid='',name='',email='',organisation='',country='',city='',picture='',vcard='',changed=:changed,localchange=1,karma=-1,changed=:changed where authkey = :key");
		$stmt->bindParam(':changed', $changed, \PDO::PARAM_INT);
		$stmt->bindParam(':key', $key, \PDO::PARAM_STR);
		$stmt->execute();
	}

	/**
	 * Import data from a remote server
	 * @param array $date
	 */
	public function importReplication($data) {
		$stmt = DB::prepare('insert into user (userid,authkey,federationid,name,email,organisation,country,city,picture,vcard,karma,created,changed,localchange) values(:userid,:authkey,:federationid,:name,:email,:organisation,:country,:city,:picture,:vcard,:karma,:created,:changed,0) ON DUPLICATE KEY UPDATE userid=:userid,authkey=:authkey,federationid=:federationid,name=:name,email=:email,organisation=:organisation,country=:country,city=:city,picture=:picture,vcard=:vcard,karma=:karma,created=:created,changed=:changed,localchange=0 ');
		$stmt->bindParam(':userid', $data -> userid, \PDO::PARAM_STR);
		$stmt->bindParam(':authkey', $data -> authkey, \PDO::PARAM_STR);
		$stmt->bindParam(':federationid', $data -> federationid, \PDO::PARAM_STR);
		$stmt->bindParam(':name', $data -> name, \PDO::PARAM_STR);
		$stmt->bindParam(':email', $data -> email, \PDO::PARAM_STR);
		$stmt->bindParam(':organisation', $data -> organisation, \PDO::PARAM_STR);
		$stmt->bindParam(':country', $data -> country, \PDO::PARAM_STR);
		$stmt->bindParam(':city', $data -> city, \PDO::PARAM_STR);
		$stmt->bindParam(':picture', $data -> picture, \PDO::PARAM_STR);
		$stmt->bindParam(':vcard', $data -> vcard, \PDO::PARAM_STR);
		$stmt->bindParam(':karma', $data -> karma, \PDO::PARAM_STR);
		$stmt->bindParam(':created', $data -> created, \PDO::PARAM_INT);
		$stmt->bindParam(':changed', $data -> changed, \PDO::PARAM_INT);
		$stmt->execute();
	}

	/**
	 * Update Karma
	 */
	public function updateKarma($userid) {
		$stmt=DB::prepare("select userid,karma,email,emailstatus from user where userid=:userid");
		$stmt->bindParam(':userid', $userid, \PDO::PARAM_STR);
		$stmt->execute();
		$num=$stmt->rowCount();

		if($num==1) {
			$karma = 0;
			$content=$stmt->fetch(\PDO::FETCH_ASSOC);
			if($content['karma']==-1) return; // deleted account. nothing todo
			if($content['emailstatus']==1) $karma++;

			$stmt=DB::prepare("update user set karma=:karma where userid=:userid");
			$stmt->bindParam(':karma', $karma, \PDO::PARAM_STR);
			$stmt->bindParam(':userid', $userid, \PDO::PARAM_STR);
			$stmt->execute();
		}

	}


	/**
	 * Send Email
	 */
	public function sendEmail($to,$subject,$text) {
		$headers = 'From: '.LOOKUPSERVER_EMAIL_SENDER."\r\n" .'Reply-To: '.LOOKUPSERVER_EMAIL_SENDER."\r\n" .'X-Mailer: PHP/' . phpversion();
		mail($to, $subject, $text, $headers);
	}


	/**
	 * Start email verification
	 */
	public function startEmailVerification($authkey,$email) {
		$util = new Util();
		$key = rand(1000000000,2000000000);

		$stmt=DB::prepare("update user set emailstatus=:emailstatus,karma=0 where authkey = :authkey");
		$stmt->bindParam(':emailstatus', $key, \PDO::PARAM_STR);
		$stmt->bindParam(':authkey', $authkey, \PDO::PARAM_STR);
		$stmt->execute();

		$text = 'Please click this link to confirm your account: '.LOOKUPSERVER_PUBLIC_URL.'/verifyemail.php?key='.$key;
		$this->sendEmail($email, 'Email Confirmation', $text);
		$util -> Log('Email verification mail sent. EMAIL: '.$email);
	}

	/**
	 * Verify Email
	 */
	public function verifyEmail() {
		$util = new Util();
		if(isset($_GET['key'])) $key = $_GET['key']; else $key = '';

		$stmt=DB::prepare("select userid from user where emailstatus=:key");
		$stmt->bindParam(':key', $key, \PDO::PARAM_STR);
		$stmt->execute();
		$num=$stmt->rowCount();

		if($num==1) {
			$content=$stmt->fetch(\PDO::FETCH_ASSOC);
			$userid = $content['userid'];
			$emailstatus = 1;
			$stmt=DB::prepare("update user set emailstatus=:emailstatus where userid=:userid");
			$stmt->bindParam(':emailstatus', $emailstatus, \PDO::PARAM_STR);
			$stmt->bindParam(':userid', $userid, \PDO::PARAM_STR);
			$stmt->execute();

			$this->updateKarma($userid);

			$util -> Log('Email verified. USER: '.$userid.' KEY: '.$key);
			echo('email verified');


		} else {
			$util -> Log('Email NOT verified. KEY: '.$key);
			echo('email not verified');
		}

	}

}
