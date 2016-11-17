<?php

namespace LookupServer;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class UserManager {

    function search(Request $request, Response $response) {
        $response->getBody()->write("HELLLO HELLO");

        return $response;
    }

    function register(Request $request, Response $response) {
        $response->getBody()->write("WTF DUDEs");

        return $response;
    }
}
