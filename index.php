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


namespace LookupServer;


use LookupServer\Validator\Email;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/init.php';

if (!isset($app) || !isset($container)) {
	return;
}

$r_head = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	return $response->withHeader('X-Version', VERSION);
};
$app->map(['HEAD', 'GET'], '/', $r_head);
$app->map(['HEAD', 'GET'], '/index.php', $r_head);

$r_search = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	/** @var UserManager $userManager */
	$userManager = $this->get('UserManager');

	return $userManager->search($request, $response, $args);
};
$app->get('/users', $r_search);
$app->get('/index.php/users', $r_search);

$r_register = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	/** @var UserManager $userManager */
	$userManager = $this->get('UserManager');

	return $userManager->register($request, $response, $args);
};
$app->post('/users', $r_register);
$app->post('/index.php/users', $r_register);


$r_delete = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	/** @var UserManager $userManager */
	$userManager = $this->get('UserManager');

	return $userManager->delete($request, $response, $args);
};
$app->delete('/users', $r_delete);
$app->delete('/index.php/users', $r_delete);


$r_batchDetails = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	/** @var UserManager $userManager */
	$userManager = $this->get('UserManager');

	return $userManager->batchDetails($request, $response, $args);
};
$app->get('/gs/users', $r_batchDetails);
$app->get('/index.php/gs/users', $r_batchDetails);


$r_batchRegister = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	/** @var UserManager $userManager */
	$userManager = $this->get('UserManager');

	return $userManager->batchRegister($request, $response, $args);
};
$app->post('/gs/users', $r_batchRegister);
$app->post('/index.php/gs/users', $r_batchRegister);


$r_batchDelete = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	/** @var UserManager $userManager */
	$userManager = $this->get('UserManager');

	return $userManager->batchDelete($request, $response, $args);
};
$app->delete('/gs/users', $r_batchDelete);
$app->delete('/index.php/gs/users', $r_batchDelete);



$r_instances = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	/** @var InstanceManager $instanceManager */
	$instanceManager = $this->get('InstanceManager');

	return $instanceManager->getInstances($request, $response);
};
$app->get('/gs/instances', $r_instances);
$app->get('/index.php/gs/instances', $r_instances);
$app->post('/instances', $r_instances); // retro compatibility until nc26
$app->post('/index.php/instances', $r_instances); // retro compatibility until nc26

$r_validateEmail = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	/** @var Email $emailValidator */
	$emailValidator = $this->get('EmailValidator');

	return $emailValidator->validate($request, $response, $args);
};
$app->get('/validate/email/{token}', $r_validateEmail);
$app->get('/index.php/validate/email/{token}', $r_validateEmail);


$r_status = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	$response->getBody()->write(
		json_encode(
			['version' => VERSION]
		)
	);

	return $response;
};
$app->get('/status', $r_status);
$app->get('/index.php/status', $r_status);


$r_export = function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
	/** @var Replication $replication */
	$replication = $this->get('Replication');

	return $replication->export($request, $response, $args);
};
$app->get('/replication', $r_export);
$app->get('/index.php/replication', $r_export);


$app->run();
