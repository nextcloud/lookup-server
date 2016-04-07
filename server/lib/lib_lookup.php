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

// include all the required libraries and configuration file
require('config/config.php');
require('config/version.php');
require('lib_db.php');
require('lib_util.php');
require('lib_bruteforce.php');
require('lib_data.php');

/**
 * The main class of the Lookup Server
 */
class LookupServer {

	/**
	 * Handle an incoming REST call
	 */
	public function handlerequest() {
		$util = new LookupServer_Util();

		if(!isset($_SERVER['REQUEST_METHOD'])) $util->error('no request method');
		$method = $_SERVER['REQUEST_METHOD'];

		switch ($method) {
		  case 'PUT':
		    $this->updateuser();
		    break;
		  case 'POST':
		    $this->createuser();
		    break;
		  case 'GET':
		    if(isset($_GET['search'])) {
				$this->searchUsers();
			}elseif(isset($_GET['email'])) {
				$this->getUserByEmail($_GET['email']);
			}elseif(isset($_GET['userid'])) {
				$this->getUserByUserId($_GET['userid']);
			} else {
				$this->getUserByKey();
			}
		    break;
		  case 'DELETE':
		    $this->deleteuser();
		    break;
		  default:
		    $util->error('invalid request');
		}

	}

	/**
	 * Handle an incoming Replication REST call
	 */
	public function handleReplication() {
		$util = new LookupServer_Util();

		if(!isset($_SERVER['REQUEST_METHOD'])) $util->error('no request method');
		$method = $_SERVER['REQUEST_METHOD'];

		if($method == 'GET' and isset($_GET['timestamp']) and isset($_SERVER['PHP_AUTH_PW'])) {

			if(isset($_SERVER['PHP_AUTH_PW']) and isset($_SERVER['PHP_AUTH_USER']) and ($_SERVER['PHP_AUTH_PW']==LOOKUPSERVER_REPLICATION_AUTH) and (LOOKUPSERVER_REPLICATION_AUTH<>'foobar')  ) {
				$this->exportReplication(false);
			}elseif(isset($_SERVER['PHP_AUTH_PW']) and isset($_SERVER['PHP_AUTH_USER']) and ($_SERVER['PHP_AUTH_PW']==LOOKUPSERVER_SLAVEREPLICATION_AUTH) and (LOOKUPSERVER_SLAVEREPLICATION_AUTH<>'slavefoobar')  ) {
					$this->exportReplication(true);
			} else {
				$util -> replicationLog('Invalid Replication auth: '.$_SERVER['PHP_AUTH_PW']);
		    	$util->error('Invalid replication auth');
			}

		} else {
		    $util->error('invalid replication request');
		}

	}

	/**
	 *  Get User
	 */
	public function getUserByKey() {
		if(isset($_GET['key'])) {
			$util = new LookupServer_Util();
			$util -> log('GET USER BY KEY: '.$_GET['key']);
			$data = new LookupServer_Data();
			$user = $data -> getByKey($_GET['key']);
			echo(json_encode($user,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Get User by email
	 */
	public function getUserByEmail() {
		if(isset($_GET['email'])) {
			$util = new LookupServer_Util();
			$util -> log('GET USER BY EMAIL: '.$_GET['email']);
			$data = new LookupServer_Data();
			$user = $data -> getByEmail($_GET['email']);
			echo(json_encode($user,JSON_PRETTY_PRINT));
		}
	}

	/**
	 *  Get User by userid
	 */
	public function getUserByUserId() {
		if(isset($_GET['userid'])) {
			$util = new LookupServer_Util();
			$util -> log('GET USER BY USERID: '.$_GET['userid']);
			$data = new LookupServer_Data();
			$user = $data -> getByUserId($_GET['userid']);
			echo(json_encode($user,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Search Users
	 */
	public function searchusers() {
		$pagesize = 10;
		if(isset($_GET['search']) and isset($_GET['page'])) {
			$util = new LookupServer_Util();
			$util -> log('SEARCH USER : '.$_GET['search'].' PAGE:'.$_GET['page']);
			if($_GET['page'] > LOOKUPSERVER_MAX_SEARCH_PAGE) {
				$util = new LookupServer_Util();
				$util->error('page number is too high');
			}
			$data = new LookupServer_Data();
			$users = $data -> searchuser($_GET['search'], $_GET['page']*$pagesize, $pagesize);
			echo(json_encode($users,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Create User
	 */
	public function createuser() {
		$util = new LookupServer_Util();
		if(isset($_POST['key']) and
		isset($_POST['federationid']) and
		isset($_POST['name']) and
		isset($_POST['email']) and
		isset($_POST['organisation']) and
		isset($_POST['country']) and
		isset($_POST['city']) and
		isset($_POST['picture']) and
		isset($_POST['vcard'])
		){
			$key          = $util -> sanitize($_POST['key']);
			$federationid = $util -> sanitize($_POST['federationid']);
			$name         = $util -> sanitize($_POST['name']);
			$email        = $util -> sanitize($_POST['email']);
			$organisation = $util -> sanitize($_POST['organisation']);
			$country      = $util -> sanitize($_POST['country']);
			$city         = $util -> sanitize($_POST['city']);
			$picture      = $util -> sanitize($_POST['picture']);
			$vcard        = $util -> sanitize($_POST['vcard']);

			$util -> log('CREATE USER : '.$key);

			$d = new LookupServer_Data();
			$user = $d -> userExist($key);
			if(!$user) {
				$d -> store($key,$federationid,$name,$email,$organisation,$country,$city,$picture,$vcard);
			} else {
				$d -> update($key,$federationid,$name,$email,$organisation,$country,$city,$picture,$vcard);
			}
			echo(json_encode(true,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Update User
	 */
	public function updateuser() {
		$util = new LookupServer_Util();
		parse_str(file_get_contents('php://input'), $PUT);

		if(isset($PUT['key']) and
		isset($PUT['federationid']) and
		isset($PUT['name']) and
		isset($PUT['email']) and
		isset($PUT['organisation']) and
		isset($PUT['country']) and
		isset($PUT['city']) and
		isset($PUT['picture']) and
		isset($PUT['vcard'])
		){
			$key          = $util -> sanitize($PUT['key']);
			$federationid = $util -> sanitize($PUT['federationid']);
			$name         = $util -> sanitize($PUT['name']);
			$email        = $util -> sanitize($PUT['email']);
			$organisation = $util -> sanitize($PUT['organisation']);
			$country      = $util -> sanitize($PUT['country']);
			$city         = $util -> sanitize($PUT['city']);
			$picture      = $util -> sanitize($PUT['picture']);
			$vcard        = $util -> sanitize($PUT['vcard']);
			$util -> log('UPDATE USER : '.$key);

			$d = new LookupServer_Data();
			$d -> update($key,$federationid,$name,$email,$organisation,$country,$city,$picture,$vcard);
			echo(json_encode(true,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Delete User
	 */
	public function deleteuser() {
		$data = new LookupServer_Data();
		if(isset($_GET['key'])) {
			$util = LookupServer_Util();
			$util -> log('DELETE USER : '.$_GET['key']);
			$data -> deleteByKey($_GET['key']);
			echo(json_encode(true,JSON_PRETTY_PRINT));
		}
	}

	/**
	 *  Get users for replication
	 */
	public function exportReplication($slave) {
		$pagesize = 10;
		if(isset($_GET['fullfetch'])) $fullfetch = true; else $fullfetch = false;
		if(isset($_GET['timestamp']) and isset($_GET['page'])) {
			$util = new LookupServer_Util();
			$util -> replicationLog('GET TIMESTAMP: '.$_GET['timestamp'].' PAGE: '.$_GET['page'].' FULLFETCH: '.json_encode($fullfetch).' SLAVE: '.json_encode($slave));
			$data = new LookupServer_Data();
			$users = $data -> exportReplication($_GET['timestamp'], $_GET['page']*$pagesize, $pagesize, $fullfetch, $slave);
			echo(json_encode($users,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Import replication log
	 */
	public function importReplication() {
		global $LOOKUPSERVER_REPLICATION_HOSTS;
		$data = new LookupServer_Data();
		$util = new LookupServer_Util();

		foreach($LOOKUPSERVER_REPLICATION_HOSTS as $host) {
			$timestamp = time() - LOOKUPSERVER_REPLICATION_INTERVAL;
			$page=0;
			$count=1;
			while($count<>0) {
				$util -> replicationLog('FETCH HOST: '.$host.' TIMESTAMP: '.$timestamp.' PAGE: '.$page);
				$replicationdata = file_get_contents($host.'/replication.php?timestamp='.$timestamp.'&page='.$page);
				$entries = json_decode($replicationdata);
//				print_r($entries);
				$count = count($entries);
				for ($i = 0; $i < $count; $i++) $data -> importReplication($entries[$i]);
				$page++;
			}
		}
	}


	/**
	 *  Cleanup
	 */
	public function cleanup() {
		// cleanup the traffic limit DB table
		$bf = new LookupServer_BruteForce();
		$bf -> cleanupTrafficLimit();
	}


}
