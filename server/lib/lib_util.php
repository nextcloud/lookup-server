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
* The LookUp util class
*/
class LookupServer_Util {

	/**
	* Handle error
	* @param string $text
	*/
	public function error($text) {
		error_log($text);
		$this -> log($text);
		if(LOOKUPSERVER_ERROR_VERBOSE) echo(json_encode(array('error' => $text)));
		exit;
	}

	/**
	*  Generate random userid
	* @return string $userids
	*/
	public function generateUserId() {
		return(rand(1,9200000000000000000)); // mysql bigint
	}

	/**
	 *  Sanitize some input
	 * @param string $text
	 */
	public function sanitize($text) {
		$found = false;
		// search in all bad ip ranges for a match with the current ip
		foreach($GLOBALS['LOOKUPSERVER_SPAM_BLACKLIST'] as $bad_word) {
			if(stripos($text, $bad_word) <> false) $found = true;
		}
		if($found) {
			$util = new LookupServer_Util();
			$util -> log('SPAM WORD FOUND IN: '.$text);
			exit;
		}
		return(strip_tags($text));
	}

	/**
	 *  Logfile handler
	 * @param string $text
	 */
	public function log($text) {
		if(LOOKUPSERVER_LOG<>'') {
			file_put_contents(LOOKUPSERVER_LOG, $_SERVER['REMOTE_ADDR'].' '.'['.date('c').']'.' '.$text."\n", FILE_APPEND);
		}
	}

	/**
	 *  Replication Logfile handler
	 * @param string $text
	 */
	public function replicationLog($text) {
		if(LOOKUPSERVER_REPLICATION_LOG<>'') {
			if(isset($_SERVER['REMOTE_ADDR'])) $remote_addr = $_SERVER['REMOTE_ADDR']; else $remote_addr = 'local';
			file_put_contents(LOOKUPSERVER_REPLICATION_LOG, $remote_addr.' '.'['.date('c').']'.' '.$text."\n", FILE_APPEND);
		}
	}


}
