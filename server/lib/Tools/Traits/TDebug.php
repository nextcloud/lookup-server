<?php

namespace LookupServer\Tools\Traits;


trait TDebug {

	public function debug(string $line) {
		// this is ugly, but at least we have some debug somewhere
		@file_put_contents(__DIR__ . '/../../debug.log', $line . "\n", FILE_APPEND);
	}

}
