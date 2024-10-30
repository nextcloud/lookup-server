<?php

declare(strict_types=1);


/**
 * lookup-server - Standalone Lookup Server.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2022
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace LookupServer\Service;

use Abraham\TwitterOAuth\TwitterOAuth;
use DI\Container;
use Exception;
use LookupServer\InstanceManager;
use LookupServer\Replication;
use LookupServer\SignatureHandler;
use LookupServer\Tools\Traits\TArrayTools;
use LookupServer\UserManager;
use LookupServer\Validator\Email;
use LookupServer\Validator\Twitter;
use LookupServer\Validator\Website;
use PDO;
use Slim\App;

class DependenciesService {
	use TArrayTools;

	private array $settings;

	public function __construct(array $settings = []) {
		$this->settings = $settings;
	}


	/**
	 * @param Container $container
	 * @param App $app
	 */
	public function initContainer(Container $container, App $app): void {

		$container->set('db', function (Container $c) {
			$db = $this->getArray('settings.db', $c->get('Settings'));
			if (empty($db)) {
				throw new Exception('faulty configuration');
			}

			$pdo = new PDO(
				"mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
				$db['user'], $db['pass']
			);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

			return $pdo;
		});

		$container->set('SecurityService', function (Container $c) {
			$settings = $c->get('Settings');
			return new SecurityService($settings);
		});

		$container->set('InstanceManager', function (Container $c) {
			return new InstanceManager(
				$c->get('db'),
				$c->get('SecurityService'),
				$this->getArray('settings.instances', $c->get('Settings'))
			);
		});

		$container->set('UserManager', function (Container $c) {
			return new UserManager(
				$c->get('db'),
				$c->get('EmailValidator'),
				$c->get('WebsiteValidator'),
				$c->get('TwitterValidator'),
				$c->get('InstanceManager'),
				$c->get('SignatureHandler'),
				$c->get('SecurityService')
			);
		});


		$container->set('SignatureHandler', function () {
			return new SignatureHandler();
		});

		$container->set('TwitterOAuth', function (Container $c) {
			/** @var array $settings */
			$settings = $c->get('Settings');

			return new TwitterOAuth(
				$this->get('settings.twitter.consumer_key', $settings),
				$this->get('settings.twitter.consumer_secret', $settings),
				$this->get('settings.twitter.access_token', $settings),
				$this->get('settings.twitter.access_token_secret', $settings)
			);
		});


		$container->set('EmailValidator', function (Container $c) use ($app) {
			$settings = $c->get('Settings');

			return new Email(
				$c->get('db'),
				$app->getRouteCollector()->getRouteParser(),
				$this->get('settings.host', $settings),
				$this->get('settings.emailfrom', $settings),
				$c->get('SecurityService'),
			);
		});

		$container->set('WebsiteValidator', function (Container $c) {
			return new Website($c->get('SignatureHandler'));
		});


		$container->set('TwitterValidator', function (Container $c) {
			return new Twitter(
				$c->get('TwitterOAuth'),
				$c->get('SignatureHandler'),
				$c->get('db')
			);
		});

		$container->set('Replication', function (Container $c) {
			$settings = $c->get('Settings');

			return new Replication(
				$c->get('db'),
				$c->get('SecurityService'),
				$this->getArray('settings.replication_hosts', $settings)
			);
		});
	}

}
