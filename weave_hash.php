<?php

# ***** BEGIN LICENSE BLOCK *****
# Version: MPL 1.1/GPL 2.0/LGPL 2.1
#
# The contents of this file are subject to the Mozilla Public License Version
# 1.1 (the "License"); you may not use this file except in compliance with
# the License. You may obtain a copy of the License at
# http://www.mozilla.org/MPL/
#
# Software distributed under the License is distributed on an "AS IS" basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
# for the specific language governing rights and limitations under the
# License.
#
# The Original Code is http://stackoverflow.com/a/6337021/833893
#
# Contributor(s):
#   Daniel Triendl <daniel@pew.cc>
#
# Alternatively, the contents of this file may be used under the terms of
# either the GNU General Public License Version 2 or later (the "GPL"), or
# the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
# in which case the provisions of the GPL or the LGPL are applicable instead
# of those above. If you wish to allow use of your version of this file only
# under the terms of either the GPL or the LGPL, and not to allow others to
# use your version of this file under the terms of the MPL, indicate your
# decision by deleting the provisions above and replace them with the notice
# and other provisions required by the GPL or the LGPL. If you do not delete
# the provisions above, a recipient may use your version of this file under
# the terms of any one of the MPL, the GPL or the LGPL.
#
# ***** END LICENSE BLOCK *****

interface WeaveHash {
	public function hash($input);
	public function verify($input, $existingHash);
	public function needsUpdate($existingHash);
}

class WeaveHashMD5 implements WeaveHash {
	public function hash($input) {
		return md5($input);
	}

	public function verify($input, $existingHash) {
		return $this->hash($input) == $existingHash;
	}

	public function needsUpdate($existingHash) {
		return substr($existingHash, 0, 4) == "$2a$";
	}
}

class WeaveHashBCrypt implements  WeaveHash {
	private $_rounds;

	public function  __construct($rounds = 12) {
		if(CRYPT_BLOWFISH != 1) {
			throw new Exception("bcrypt not available");
		}

		$this->_rounds = $rounds;
	}

	public function hash($input) {
		$hash = crypt($input, $this->getSalt());

		if (strlen($hash) <= 13) {
			throw new Exception("error while generating hash");
		}

		return $hash;
	}

	public function verify($input, $existingHash) {
		if ($this->isMD5($existingHash)) {
			$md5 = new WeaveHashMD5();
			return $md5->verify($input, $existingHash);
		}
		
		$hash = crypt($input, $existingHash);

		return $hash === $existingHash;
	}

	public function needsUpdate($existingHash) {
		$identifier = $this->getIdentifier();
		return substr($existingHash, 0, strlen($identifier)) != $identifier;
	}
	
	private function isMD5($existingHash) {
		return substr($existingHash, 0, 4) != "$2a$";
	}

	private function getSalt() {
		$salt = $this->getIdentifier();

		$bytes = $this->getRandomBytes(16);

		$salt .= $this->encodeBytes($bytes);

		return $salt;
	}
	
	private function getIdentifier() {
		return sprintf("$2a$%02d$", $this->_rounds);
	}

	private $randomState;
	private function getRandomBytes($count) {
		$bytes = '';

		if(function_exists('openssl_random_pseudo_bytes') &&
				(strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')) { // OpenSSL slow on Win
			$bytes = openssl_random_pseudo_bytes($count);
		}

		if($bytes === '' && is_readable('/dev/urandom') &&
				($hRand = @fopen('/dev/urandom', 'rb')) !== FALSE) {
			$bytes = fread($hRand, $count);
			fclose($hRand);
		}

		if(strlen($bytes) < $count) {
			$bytes = '';

			if($this->randomState === null) {
				$this->randomState = microtime();
				if(function_exists('getmypid')) {
					$this->randomState .= getmypid();
				}
			}

			for($i = 0; $i < $count; $i += 16) {
				$this->randomState = md5(microtime() . $this->randomState);

				if (PHP_VERSION >= '5') {
					$bytes .= md5($this->randomState, true);
				} else {
					$bytes .= pack('H*', md5($this->randomState));
				}
			}

			$bytes = substr($bytes, 0, $count);
		}

		return $bytes;
	}

	private function encodeBytes($input) {
		// The following is code from the PHP Password Hashing Framework
		$itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

		$output = '';
		$i = 0;
		do {
			$c1 = ord($input[$i++]);
			$output .= $itoa64[$c1 >> 2];
			$c1 = ($c1 & 0x03) << 4;
			if ($i >= 16) {
				$output .= $itoa64[$c1];
				break;
			}

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 4;
			$output .= $itoa64[$c1];
			$c1 = ($c2 & 0x0f) << 2;

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 6;
			$output .= $itoa64[$c1];
			$output .= $itoa64[$c2 & 0x3f];
		} while (1);

		return $output;
	}
}

class WeaveHashFactory {
	public static function factory() {
		if (defined("BCRYPT") && BCRYPT) {
			return new WeaveHashBCrypt(BCRYPT_ROUNDS);
		} else {
			return new WeaveHashMD5();
		}
	}
}

?>