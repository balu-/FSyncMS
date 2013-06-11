<?php

define("BCRYPT", true);
define("BCRYPT_ROUNDS", 12);

require_once __DIR__ . '/../weave_hash.php';

$pwd = "asdfASDFghjkGHJK2134$%&";

try {
	$hash = WeaveHashFactory::factory();
	$time_start = microtime(true);
	echo $hash->hash($pwd) . "\n";
	$time = microtime(true) - $time_start;
	echo "Hashing took " . $time . " seconds\n";

	if (!$hash->verify($pwd, '$2a$12$O2Bn6lDUYS5NDIJ1uCZjGezSI/jeGTD7Ow0bd3PFMRBcGIqfqI4Oi')) {
		throw new Exception("bcrypt hash compare failed");
	}

	if (!$hash->needsUpdate(md5($pwd))) {
		throw new Exception("bcrypt hash needs update.");
	}

	if ($hash->needsUpdate('$2a$12$O2Bn6lDUYS5NDIJ1uCZjGezSI/jeGTD7Ow0bd3PFMRBcGIqfqI4Oi')) {
		throw new Exception("bcrypt hash doesn't needs update.");
	}
	
	if (!$hash->verify($pwd, 'a96b71c678b01b98b9f7a0d8ec4b633b')) {
		throw new Exception("bcrypt hash compare with md5 failed");
	}

	$hash2 = new WeaveHashBCrypt(6);

	if (!$hash2->needsUpdate('$2a$12$O2Bn6lDUYS5NDIJ1uCZjGezSI/jeGTD7Ow0bd3PFMRBcGIqfqI4Oi')) {
		throw new Exception("bcrypt hash needs update because of different rounds.");
	}

	$hashmd5 = new WeaveHashMD5();
	if (!$hashmd5->verify($pwd, 'a96b71c678b01b98b9f7a0d8ec4b633b')) {
		throw new Exception("md5 hash compare failed");
	}

	if (!$hashmd5->needsUpdate('$2a$12$O2Bn6lDUYS5NDIJ1uCZjGezSI/jeGTD7Ow0bd3PFMRBcGIqfqI4Oi')) {
		throw new Exception("md5 hash needs update.");
	}

	if ($hashmd5->needsUpdate(md5($pwd))) {
		throw new Exception("md5 hash doesn't need update.");
	}

	echo "all tests ok\n";
	exit(0);
} catch(Exception $e) {
	echo $e->getMessage() . "\n";
	exit(1);
}

?>