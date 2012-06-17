<?php

    require_once 'weave_utils.php';
    if(!$include) //file should only be used in context of index.php
    {
        log_error("include error");
        report_problem('Function not found', 404);
    }
    require_once "settings.php";
    #Basic path extraction and validation. No point in going on if these are missing
	$path = '/';
	if (!empty($_SERVER['PATH_INFO']))
		$path = $_SERVER['PATH_INFO'];
	else if (!empty($_SERVER['ORIG_PATH_INFO']))
		$path = $_SERVER['ORIG_PATH_INFO'];
	else
    {
        log_error("user.php: No path found");
		report_problem("No path found", 404);
    }
	$path = substr($path, 1); #chop the lead slash
	list($preinstr,$version, $username, $function, $collection, $id) = explode('/', $path.'///');
    log_error("Pfad:".$path); 
    if( $preinstr != 'user' && $preinstr!='misc')
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
                //to be compatible with users how use /index.php/ in their path
                /*$index ="https://";
                if (!isset($_SERVER['HTTPS'])) 
                    $index = "http://";
                $index .= $_SERVER['SERVER_NAME']. dirname($_SERVER['SCRIPT_NAME']) . "/";
                if(strpos($_SERVER['REQUEST_URI'],'index.php') !== 0)
                    $index .= "index.php/";
                //antwort (self)i*/
                exit(FSYNCMS_ROOT);
                    
            }
            else if($function == 'password_reset')
            {
                //email mit neuem pw senden
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
                report_problem(WEAVE_ERROR_INVALID_PROTOCOL, 400);
            $name = $username;
            $pwd = fix_utf8_encoding($data['password']);
            $email = $data['email'];
            if($name == '' || $pwd == '' || $email == '')
            {
                log_error('create user datenfehler');
                report_problem(WEAVE_ERROR_INVALID_PROTOCOL, 400);
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
                    report_problem('Authentication failed', '401');
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
