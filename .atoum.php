<?php
//.atoum.php

date_default_timezone_set('UTC');

use mageekguy\atoum;
use mageekguy\atoum\reports;

if(getenv("COVERALLS_REPO_TOKEN")) {
	$coveralls = new reports\asynchronous\coveralls(".", getenv("COVERALLS_REPO_TOKEN"));
	$coveralls->addDefaultWriter();

	$runner->addReport($coveralls);
	$script->addDefaultReport();
}
