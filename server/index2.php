<?php

require 'vendor/autoload.php';

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['host']   = "localhost";
$config['db']['user']   = "user";
$config['db']['pass']   = "password";
$config['db']['dbname'] = "exampleapp";

$c = new \Slim\Container();
$c['UserManager'] = function() {
    return new \LookupServer\UserManager();
};

$app = new \Slim\App($c);
$app->add(new \LookupServer\BruteForceMiddleware());



$app->get('/users', 'UserManager:search');
$app->post('/users', 'UserManager:register');

$app->run();
