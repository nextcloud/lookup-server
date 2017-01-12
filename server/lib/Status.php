<?php

namespace LookupServer;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Status {

	public function status(Request $request, Response $response) {
		require __DIR__ . '/../config/version.php';

		$response->getBody()->write(json_encode(array('version'=>$VERSION)));
	}
}
