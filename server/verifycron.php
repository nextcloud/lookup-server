<?php
/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
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

require __DIR__ . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    return;
}

$env = \Slim\Http\Environment::mock(['REQUEST_URI' => '/verify']);

$settings = require __DIR__ . '/src/config.php';
$settings['environment'] = $env;
$container = new \Slim\Container($settings);
require __DIR__ . '/src/dependencies.php';

$app = new \Slim\App($container);

$app->map(['GET'], '/verify', 'UserManager:verify');
$app->run();
