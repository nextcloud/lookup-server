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
require('config.php');
require('lib_db.php');
require('lib_data.php');

/**
 * The main class of the Lookup Server
 */
class LookupServer {

	/**
	 * Handle an incoming REST call
	 */
	public function handlerequest() {

		if(!isset($_SERVER['REQUEST_METHOD'])) $this->error('no request method');
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
				$this->searchusers();
			} else {
				$this->getuser();
			}
		    break;
		  case 'DELETE':
		    $this->deleteuser();
		    break;
		  default:
		    $this->error('invalid request');
		}

	}


	/**
	 *  Get User
	 */
	public function getuser() {
		if(isset($_GET['key'])) {
			$data = new LookupServer_Data();
			$user = $data -> getByKey($_GET['key']);
			echo(json_encode($user,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Search Users
	 */
	public function searchusers() {
		$pagesize = 10;
		if(isset($_GET['search']) and isset($_GET['page'])) {
			$data = new LookupServer_Data();
			$users = $data -> searchuser($_GET['search'], $_GET['page']*$pagesize, $pagesize);
			echo(json_encode($users,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Create User
	 */
	public function createuser() {
		if(isset($_POST['key']) and
		isset($_POST['federationid']) and
		isset($_POST['name']) and
		isset($_POST['email']) and
		isset($_POST['country']) and
		isset($_POST['city']) and
		isset($_POST['picture']) and
		isset($_POST['vcard'])
		){
			$key          = $this -> sanitize($_POST['key']);
			$federationid = $this -> sanitize($_POST['federationid']);
			$name         = $this -> sanitize($_POST['name']);
			$email        = $this -> sanitize($_POST['email']);
			$country      = $this -> sanitize($_POST['country']);
			$city         = $this -> sanitize($_POST['city']);
			$picture      = $this -> sanitize($_POST['picture']);
			$vcard         = $this -> sanitize($_POST['vcard']);

			$d = new LookupServer_Data();
			$d -> store($key,$federationid,$name,$email,$country,$city,$picture,$vcard);
			echo(json_encode(true,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Update User
	 */
	public function updateuser() {
		parse_str(file_get_contents('php://input'), $PUT);

		if(isset($PUT['key']) and
		isset($PUT['federationid']) and
		isset($PUT['name']) and
		isset($PUT['email']) and
		isset($PUT['country']) and
		isset($PUT['city']) and
		isset($PUT['picture']) and
		isset($PUT['vcard'])
		){
			$key          = $this -> sanitize($PUT['key']);
			$federationid = $this -> sanitize($PUT['federationid']);
			$name         = $this -> sanitize($PUT['name']);
			$email        = $this -> sanitize($PUT['email']);
			$country      = $this -> sanitize($PUT['country']);
			$city         = $this -> sanitize($PUT['city']);
			$picture      = $this -> sanitize($PUT['picture']);
			$vcard         = $this -> sanitize($PUT['vcard']);

			$d = new LookupServer_Data();
			$d -> update($key,$federationid,$name,$email,$country,$city,$picture,$vcard);
			echo(json_encode(true,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Delete User
	 */
	public function deleteuser() {
		$data = new LookupServer_Data();
		if(isset($_GET['key'])) {
			$data -> deleteByKey($_GET['key']);
			echo(json_encode(true,JSON_PRETTY_PRINT));
		}
	}


	/**
	 *  Cleanup
	 */
	public function cleanup() {
	}


	/**
	 *  Sanitize some input
	 * @param string $text
	 */
	public function sanitize($text) {
		return(strip_tags($text));
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
