<?php

use DI\Container;
use LookupServer\Service\DependenciesService;
use LookupServer\UserManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

$settings = require __DIR__ . '/src/config.php';
$container->set('Settings', function ($c) use ($settings) {
	return $settings;
});


$container->set('DependenciesService', function ($c) {
	return new DependenciesService($c->get('Settings'));
});


$container->get('DependenciesService')->initContainer($container);
//require __DIR__ . '/src/dependencies.php';


//
//

//$container->set('BruteForceMiddleware', function (Container $c) {
//	return new \LookupServer\BruteForceMiddleware($c->get('db'));
//});


//$app->add(function (
//	\Psr\Http\Message\ServerRequestInterface $request,
//	\Psr\Http\Server\RequestHandlerInterface $handler,
//	$next
//) {
//	return $next($request, $handler);
//});

$app->setBasePath('/index.php');

$app->get(
	'/users',
	function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
		/** @var UserManager $userManager */
		$userManager = $this->get('UserManager');

		return $userManager->search($request, $response, $args);
	}
);

$app->post(
	'/users',
	function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
		/** @var UserManager $userManager */
		$userManager = $this->get('UserManager');

		return $userManager->register($request, $response, $args);
	}
);


//$app->post('/gs/users', 'UserManager:batchRegister');
//$app->delete('/gs/users', 'UserManager:batchDelete');
//$app->delete('/users', 'UserManager:delete');
//$app->get('/validate/email/{token}', 'EmailValidator:validate')->setName('validateEmail');
//$app->get('/status', 'Status:status');
//
//$app->get('/replication', 'Replication:export');

$app->run();
