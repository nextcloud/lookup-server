<?php


use LookupServer\Console\InstancesList;
use LookupServer\Console\InstancesRemove;
use LookupServer\Console\InstancesSync;


$container['InstancesList'] = function ($c) {
	return new InstancesList($c->InstanceManager);
};
$container['InstancesRemove'] = function ($c) {
	return new InstancesRemove($c->InstanceManager);
};
$container['InstancesSync'] = function ($c) {
	return new InstancesSync($c->InstanceManager);
};
