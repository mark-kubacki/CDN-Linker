<?php
//.atoum.php

date_default_timezone_set('UTC');

use mageekguy\atoum;
use mageekguy\atoum\reports;

if(getenv('COVERALLS_REPO_TOKEN') &&
   version_compare(PHP_VERSION, '7.0.0', '>=')) {
	$coveralls = new reports\asynchronous\coveralls(".", getenv("COVERALLS_REPO_TOKEN"));
	$coveralls->addDefaultWriter();

	$runner->addReport($coveralls);
	$script->addDefaultReport();
}
