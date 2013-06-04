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
# The Initial Developer of the Original Code is balu
#
# Portions created by the Initial Developer are Copyright (C) 2012
# the Initial Developer. All Rights Reserved.
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

	#Error constants
	define ('WEAVE_ERROR_INVALID_PROTOCOL', 1);
	define ('WEAVE_ERROR_INCORRECT_CAPTCHA', 2);
	define ('WEAVE_ERROR_INVALID_USERNAME', 3);
	define ('WEAVE_ERROR_NO_OVERWRITE', 4);
	define ('WEAVE_ERROR_USERID_PATH_MISMATCH', 5);
	define ('WEAVE_ERROR_JSON_PARSE', 6);
	define ('WEAVE_ERROR_MISSING_PASSWORD', 7);
	define ('WEAVE_ERROR_INVALID_WBO', 8);
	define ('WEAVE_ERROR_BAD_PASSWORD_STRENGTH', 9);
	define ('WEAVE_ERROR_INVALID_RESET_CODE', 10);
	define ('WEAVE_ERROR_FUNCTION_NOT_SUPPORTED', 11);
	define ('WEAVE_ERROR_NO_EMAIL', 12);
	define ('WEAVE_ERROR_INVALID_COLLECTION', 13);


    define ('LOG_THE_ERROR', 0);

    function log_error($msg) 
    {   
        if ( LOG_THE_ERROR == 1 ) 
        {
            $datei = fopen("/tmp/FSyncMS-error.txt","a");
            $fmsg = sprintf("$msg\n");
            fputs($datei,$fmsg);
            fputs($datei,"Server ".print_r( $_SERVER, true));
            fclose($datei);
        }
    }
    
	function report_problem($message, $code = 503)
	{
		$headers = array('400' => '400 Bad Request',
					'401' => '401 Unauthorized',
					'403' => '403 Forbidden',
					'404' => '404 Not Found',
					'412' => '412 Precondition Failed',
					'503' => '503 Service Unavailable');
		header('HTTP/1.1 ' . $headers{$code},true,$code);
		
		if ($code == 401)
		{
			header('WWW-Authenticate: Basic realm="Weave"');
		}
	    log_error($message);	
		exit(json_encode($message));
	}
	
	
	function fix_utf8_encoding($string)
	{
		if(mb_detect_encoding($string . " ", 'UTF-8,ISO-8859-1') == 'UTF-8')
			return $string;
		else
			return utf8_encode($string);
	}
    
    function get_phpinput()
    {
        #stupid php being helpful with input data...
        $putdata = fopen("php://input", "r");
        $string = '';
        while ($data = fread($putdata,2048)) {$string .= $data;} //hier will man ein limit einbauen
        return $string;
    }
	function get_json()
	{
		$jsonstring = get_phpinput();
        $json = json_decode(fix_utf8_encoding($jsonstring), true);

		if ($json === null)
			report_problem(WEAVE_ERROR_JSON_PARSE, 400);
			
		return $json;
	}

	function validate_username($username)
	{
		if (!$username)
			return false;
		
		if (strlen($username) > 32)
			return false;
			
		return preg_match('/[^A-Z0-9._-]/i', $username) ? false : true;
	}
	
	function validate_collection($collection)
	{
		if (!$collection)
			return false;
		
		if (strlen($collection) > 32)
			return false;

		// allow characters '?' and '=' in the collection string which e.g.
		// appear if the following request is send from firefox:
		// http://<server>/weave/1.1/<user>/storage/clients?full=1
		return preg_match('/[^A-Z0-9?=._-]/i', $collection) ? false : true;
	}
	
    #user exitsts
    function exists_user( $db)
    {
        #$user = strtolower($user);
        try{

            if(!$db->exists_user())
                return 0;
            return 1;
        }
        catch(Exception $e)
        {
               header("X-Weave-Backoff: 1800");
               report_problem($e->getMessage(), $e->getCode());
        }
    }
	# Gets the username and password out of the http headers, and checks them against the auth
	function verify_user($url_user, $db)
	{
		if (!$url_user || !preg_match('/^[A-Z0-9._-]+$/i', $url_user)) 
			report_problem(WEAVE_ERROR_INVALID_USERNAME, 400);

		$auth_user = array_key_exists('PHP_AUTH_USER', $_SERVER) ? $_SERVER['PHP_AUTH_USER'] : null;
		$auth_pw = array_key_exists('PHP_AUTH_PW', $_SERVER) ? $_SERVER['PHP_AUTH_PW'] : null;

		if (is_null($auth_user) || is_null($auth_pw)) 
        {
			/* CGI/FCGI auth workarounds */
			$auth_str = null;
			if (array_key_exists('Authorization', $_SERVER))
				/* Standard fastcgi configuration */
				$auth_str = $_SERVER['Authorization'];
			else if (array_key_exists('AUTHORIZATION', $_SERVER))
				/* Alternate fastcgi configuration */
				$auth_str = $_SERVER['AUTHORIZATION'];
			else if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER))
				/* IIS/ISAPI and newer (yet to be released) fastcgi */
				$auth_str = $_SERVER['HTTP_AUTHORIZATION'];
			else if (array_key_exists('REDIRECT_HTTP_AUTHORIZATION', $_SERVER))
				/* mod_rewrite - per-directory internal redirect */
				$auth_str = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
			if (!is_null($auth_str)) 
			{
				/* Basic base64 auth string */
				if (preg_match('/Basic\s+(.*)$/', $auth_str)) 
				{
					$auth_str = substr($auth_str, 6);
					$auth_str = base64_decode($auth_str, true);
					if ($auth_str != FALSE) {
						$tmp = explode(':', $auth_str);
						if (count($tmp) == 2) 
						{
							$auth_user = $tmp[0];
							$auth_pw = $tmp[1];
						}
					}
				}
			}
		}

		if ( ! $auth_user || ! $auth_pw) #do this first to avoid the cryptic error message if auth is missing
		{
            log_error("Auth failed 1 {");
            log_error(" User pw: ". $auth_user ." | ". $auth_pw);
            log_error(" Url_user: ". $url_user);
            log_error("}");
            report_problem('Authentication failed', '401');
		}
		$url_user = strtolower($url_user);
		if (strtolower($auth_user) != $url_user)
		{
            log_error("(140) Missmatch:".strtolower($auth_user)."|".$url_user);
            report_problem(WEAVE_ERROR_USERID_PATH_MISMATCH, 400);
        }

		try 
		{
			$existingHash = $db->get_password_hash();
			$hash = WeaveHashFactory::factory();
			
			if ( ! $hash->verify(fix_utf8_encoding($auth_pw), $existingHash) )
            {
                log_error("Auth failed 2 {");
                log_error(" User pw: ". $auth_user ."|".$auth_pw ."|md5:". md5($auth_pw) ."|fix:". fix_utf8_encoding($auth_pw) ."|fix md5 ". md5(fix_utf8_encoding($auth_pw)));
                log_error(" Url_user: ".$url_user);
                log_error(" Existing hash: ".$existingHash);
                log_error("}");
				report_problem('Authentication failed', '401');
            } else {
            	if ( $hash->needsUpdate($existingHash) ) {
            		$db->change_password($hash->hash(fix_utf8_encoding($auth_pw)));
            	}
            }
		}
		catch(Exception $e)
		{
			header("X-Weave-Backoff: 1800");
			log_error($e->getMessage(), $e->getCode());
			report_problem($e->getMessage(), $e->getCode());
		}

		return true;
	}

	function check_quota(&$db)
	{
		return;
	}
	
	function check_timestamp($collection, &$db)
	{
		if (array_key_exists('HTTP_X_IF_UNMODIFIED_SINCE', $_SERVER) 
			&& $db->get_max_timestamp($collection) > $_SERVER['HTTP_X_IF_UNMODIFIED_SINCE'])
				report_problem(WEAVE_ERROR_NO_OVERWRITE, 412);			
	}

	function validate_search_params()
	{
		$params = array();
		$params['parentid'] = (array_key_exists('parentid', $_GET) && mb_strlen($_GET['parentid'], '8bit') <= 64 && strpos($_GET['parentid'], '/') === false) ? $_GET['parentid'] : null;
		$params['predecessorid'] = (array_key_exists('predecessorid', $_GET) && mb_strlen($_GET['predecessorid'], '8bit') <= 64 && strpos($_GET['predecessorid'], '/') === false) ? $_GET['predecessorid'] : null;
	
		$params['newer'] = (array_key_exists('newer', $_GET) && is_numeric($_GET['newer'])) ? round($_GET['newer'],2) : null;
		$params['older'] = (array_key_exists('older', $_GET) && is_numeric($_GET['older'])) ? round($_GET['older'],2) : null;
			
		$params['sort'] = (array_key_exists('sort', $_GET) && ($_GET['sort'] == 'oldest' || $_GET['sort'] == 'newest' || $_GET['sort'] == 'index')) ? $_GET['sort'] : null;
		$params['limit'] = (array_key_exists('limit', $_GET) && is_numeric($_GET['limit']) && $_GET['limit'] > 0) ? (int)$_GET['limit'] : null;
		$params['offset'] = (array_key_exists('offset', $_GET) && is_numeric($_GET['offset']) && $_GET['offset'] > 0) ? (int)$_GET['offset'] : null;
	
		$params['ids'] = null;
		if (array_key_exists('ids', $_GET))
		{
			$params['ids'] = array();
			foreach(explode(',', $_GET['ids']) as $id)
			{
				if (mb_strlen($id, '8bit') <= 64 && strpos($id, '/') === false)
					$params['ids'][] = $id;
			}
		}
	
		$params['index_above'] = (array_key_exists('index_above', $_GET) && is_numeric($_GET['index_above']) && $_GET['index_above'] > 0) ? (int)$_GET['index_above'] : null;
		$params['index_below'] = (array_key_exists('index_below', $_GET) && is_numeric($_GET['index_below']) && $_GET['index_below'] > 0) ? (int)$_GET['index_below'] : null;
		$params['depth'] = (array_key_exists('depth', $_GET) && is_numeric($_GET['depth']) && $_GET['depth'] > 0) ? (int)$_GET['depth'] : null;
	
		return $params;
	}
	
?>
