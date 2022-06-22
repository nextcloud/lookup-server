<?php


namespace LookupServer\Service;

use Abraham\TwitterOAuth\TwitterOAuth;
use DI\Container;
use Exception;
use LookupServer\Replication;
use LookupServer\SignatureHandler;
use LookupServer\Status;
use LookupServer\Tools\Traits\TArrayTools;
use LookupServer\UserManager;
use LookupServer\Validator\Email;
use LookupServer\Validator\Twitter;
use LookupServer\Validator\Website;
use PDO;

class DependenciesService {
	use TArrayTools;

	private array $settings;

	public function __construct(array $settings) {
		$this->settings = $settings;
	}


	public function initContainer(Container $container): void {

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


		$container->set('UserManager', function (Container $c) {
			return new UserManager(
				$c->get('db'),
				$c->get('EmailValidator'),
				$c->get('WebsiteValidator'),
				$c->get('TwitterValidator'),
				$c->get('SignatureHandler'),
				$this->getBool('settings.global_scale', $c->get('Settings')),
				$this->get('settings.auth_key', $c->get('Settings'))
			);
		});


		$container->set('SignatureHandler', function () {
			return new SignatureHandler();
		});

		$container->set('TwitterOAuth', function ($c) {
			/** @var array $settings */
			$settings = $c->get('Settings');

			return new TwitterOAuth(
				$this->get('settings.twitter.consumer_key', $settings),
				$this->get('settings.twitter.consumer_secret', $settings),
				$this->get('settings.twitter.access_token', $settings),
				$this->get('settings.twitter.access_token_secret', $settings)
			);
		});


		$container->set('EmailValidator', function ($c) {
			$settings = $c->get('Settings');

			return new Email(
				$c->get('db'),
//				$c->get('RouterInterface'),
				$this->get('settings.host', $settings),
				$this->get('settings.emailfrom', $settings),
				$this->getBool('settings.global_scale', $settings)
			);
		});

		$container->set('WebsiteValidator', function ($c) {
			return new Website($c->get('SignatureHandler'));
		});


		$container->set('TwitterValidator', function ($c) {
			return new Twitter(
				$c->get('TwitterOAuth'),
				$c->get('SignatureHandler'),
				$c->get('db')
			);
		});


		$container->set('Status', function ($c) {
			return new Status();
		});


		$container->set('Replication', function ($c) {
			$settings = $c->get('Settings');

			return new Replication(
				$c->get('db'),
				$this->get('settings.replication_auth', $settings),
				$this->getArray('settings.replication_hosts', $settings)
			);
		});
	}

}
