<?php
    /*
    ## DESCRIPTION: Implementation of user api v1.0 
    ##
    ## AUTHOR: balu
    ##
    ## DATE: 20.02.2012
    ## 
    ## VERSION: 0.1
    */
    require_once 'weave_utils.php';
    if(!$include) //file should only be used in context of index.php
    {
        log_error("include error");
        report_problem('Function not found', 404);
    }
    require_once "settings.php";
	// basic path extraction and validation. No point in going on if these are missing
	$path = '/';
	if (!empty($_SERVER['PATH_INFO']))
		$path = $_SERVER['PATH_INFO'];
	else if (!empty($_SERVER['ORIG_PATH_INFO']))
		$path = $_SERVER['ORIG_PATH_INFO'];
	else if (!empty($_SERVER["REQUEST_URI"]))
	{
		// improved path handling to prevent invalid server url error message in Firefox
		log_error("experimental path");

		// this is kind of an experimental try, i needed it so i build it,
		// but that doesent mean that it does work... well it works for me
		// and it shouldnt break anything...
		$path = $_SERVER["REQUEST_URI"];
		$lastfolder = substr(FSYNCMS_ROOT,strrpos(FSYNCMS_ROOT, "/",-2));
		$path = substr($path, (strpos($path,$lastfolder) + strlen($lastfolder)-1));	// chop the lead slash
		if(strpos($path,'?') != false)
			$path = substr($path, 0, strpos($path,'?'));	// remove php arguments
		log_error("path_exp:".$path);
	}
	else 
	{
        	log_error("user.php: No path found");
			report_problem("No path found", 404);
	}
	$path = substr($path, 1); #chop the lead slash
	list($preinstr, $version, $username, $function, $collection, $id) = explode('/', $path.'///');
    log_error("Pfad:".$path); 
    if( $preinstr != 'user' && $preinstr != 'misc' )
        report_problem('Function not found', 404);
	
    if ($version != '1.0')
		report_problem('Function not found', 404);
    
	//if captcha 
    if(($preinstr =='misc') && ($_SERVER['REQUEST_METHOD'] == 'GET') && ($username =='captcha_html'))
    {
        if(ENABLE_REGISTER)
            exit("And click to the next page");
        else
            exit("Register to this Server is not permitted, sorry");
    }
    
    //probably no need but...
    header("Content-type: application/json");
    //if ($function != "info" && $function != "storage")
	//	report_problem(WEAVE_ERROR_FUNCTION_NOT_SUPPORTED, 400);
    if (!validate_username($username)) 
	{
        log_error( "invalid user");
        report_problem(WEAVE_ERROR_INVALID_USERNAME, 400);
    }
	#user passes preliminaries, connections made, onto actually getting the data
	try
	{
        if ($_SERVER['REQUEST_METHOD'] == 'GET')
        {
            $db = new WeaveStorage($username);
            log_error("user.php: GET");
            if($function == 'node' && $collection == 'weave') //client fragt node an 
            {
                // reply node server for user

                //to be compatible with users how use /index.php/ in their path
                /*$index ="https://";
                if (!isset($_SERVER['HTTPS'])) 
                    $index = "http://";
                $index .= $_SERVER['SERVER_NAME']. dirname($_SERVER['SCRIPT_NAME']) . "/";
                if(strpos($_SERVER['REQUEST_URI'],'index.php') !== 0)
                    $index .= "index.php/";
                */
                exit(FSYNCMS_ROOT);
                    
            }
            else if($function == 'password_reset')
            {
                //email mit neuem pw senden
                /*
                Possible errors:

                    503: problems with looking up the user or sending the email
                    400: 12 (No email address on file)
                    400: 3 (Incorrect or missing username)
                    400: 2 (Incorrect or missing captcha)
                */
                report_problem(WEAVE_ERROR_NO_EMAIL, 400);
            }
            //node/weave
		    else if($function == '' && $collection == '' && $id =='') //frage nach freiem usernamen
            //User exists
            {
                //$db = new WeaveStorage($username);
                if(exists_user($db))
                    exit(json_encode(1));
                else
                    exit(json_encode(0));
            }
            else
                report_problem(WEAVE_ERROR_INVALID_PROTOCOL, 400);    
        }
        else if($_SERVER['REQUEST_METHOD'] == 'PUT')
        {
            
            if(ENABLE_REGISTER)
            {
            $db = new WeaveStorage(null);
            //Requests that an account be created for username. 
            /*
            The JSON payload should include
            Field   Description
            password    The password to be associated with the account.
            email   Email address associated with the account
            captcha-challenge   The challenge string from the captcha (see miscellaneous functions below)
            captcha-response    The response to the captcha. Only required if WEAVE_REGISTER_USE_CAPTCHA is set 
            */
            log_error("PUT");
            $data = get_json();
            log_error(print_r($data,true));
            //werte vorhanden
            if($data == NULL)
                report_problem(WEAVE_ERROR_JSON_PARSE, 400);
            $name = $username;
            $pwd = fix_utf8_encoding($data['password']);
            $email = $data['email'];
            if($email == '')
            {
                log_error('create user datenfehler');
                report_problem(WEAVE_ERROR_NO_EMAIL, 400);
            }
            else if ( $pwd == '' )
            {
                log_error('create user datenfehler');
                report_problem(WEAVE_ERROR_MISSING_PASSWORD, 400);
            }
            if($name == '' || $pwd == '' || $email == '')
            {
                log_error('create user datenfehler');
                report_problem(WEAVE_ERROR_JSON_PARSE, 400);
            }
            log_error("create user ".$name." pw : ".$pwd);
            try{
                if ($db->create_user($name, $pwd))
                {
                    log_error("successfully created user");
                    exit(json_encode(strtolower($name)));
                }
                else
                {
                    log_error("create user failed");
                    report_problem(WEAVE_ERROR_NO_OVERWRITE, 503);
                }
            }
            catch(Exception $e)
            {
                log_error("db exception create user");
                header("X-Weave-Backoff: 1800");
                report_problem($e->getMessage(), $e->getCode());
            }
            
            }
            else
            {
                log_error("register not enabled");
                report_problem(WEAVE_ERROR_FUNCTION_NOT_SUPPORTED,400);
            }
        } // ende put
        else if($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            if($username == '')
            {
                log_error("user.php : Post no username");
                report_problem(WEAVE_ERROR_INVALID_USERNAME, 400);
            }
            $db = new WeaveStorage($username);
            log_error("user.php: POST");
            if($function == "password")
            {
                #Auth the user
                verify_user($username, $db);
                   $new_pwd = get_phpinput();
                   log_error("user.php: POST password ");
                  //to do
                  // change pw in db
                  if($db->change_password($username, $new_pwd))
                    exit("success"); 
                  else
                    report_problem(WEAVE_ERROR_INVALID_PROTOCOL, 503); //server db messed up somehow
                  // return success
                  // report_problem(7, 400);
            }
            else if($function == "email")
            {
                //change email adr
            }
            else
            {
                report_problem(WEAVE_ERROR_INVALID_PROTOCOL, 400);
            }
            // exit('success');
        }
    }
    catch(Exception $e)
    {
           report_problem($e->getMessage(), $e->getCode());
    }
#The datasets we might be dealing with here are too large for sticking it all into an array, so
#we need to define a direct-output method for the storage class to use. If we start producing multiples
#(unlikely), we can put them in their own class.

#include_once "WBOJsonOutput.php";

?>
