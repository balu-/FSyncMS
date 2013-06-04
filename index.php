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
# The Original Code is Weave Minimal Server
#
# The Initial Developer of the Original Code is
# Mozilla Labs.
# Portions created by the Initial Developer are Copyright (C) 2008
# the Initial Developer. All Rights Reserved.
#
# Contributor(s):
#	Toby Elliott (telliott@mozilla.com)
#   Luca Tettamanti
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

    if ( ! file_exists("settings.php") && file_exists("setup.php") ) {
        require_once "setup.php";
        exit;

    } else if ( ! file_exists("settings.php") ) {
        echo "<hr><h2>Maybe the setup is not completed, missing settings.php</h2><hr>"; 
        exit;

    } else if ( file_exists("setup.php") ) {
        echo "<hr><h2>Maybe the setup is not completed, else please delete setup.php</h2><hr>"; 
        exit;
    }

	require_once 'weave_storage.php';
	require_once 'weave_basic_object.php';
	require_once 'weave_utils.php';
	require_once 'weave_hash.php';

    require_once "WBOJsonOutput.php";
	//header("Content-type: application/json");

	$server_time = round(microtime(1), 2);
	header("X-Weave-Timestamp: " . $server_time);

	# Basic path extraction and validation. No point in going on if these are missing
	$path = '/';
	if (!empty($_SERVER['PATH_INFO']))
		$path = $_SERVER['PATH_INFO'];
	else if (!empty($_SERVER['ORIG_PATH_INFO']))
		$path = $_SERVER['ORIG_PATH_INFO'];
    else if (!empty($_SERVER["REQUEST_URI"]))
    {
        log_error("experimental path");
        # this is kind of an experimental try, i needed it so i build it,
        # but that doesent mean that it does work... well it works for me
        # and it shouldnt break anything...
        $path = $_SERVER["REQUEST_URI"];
        $lastfolder = substr(FSYNCMS_ROOT,strrpos(FSYNCMS_ROOT, "/",-2));
        $path = substr($path, (strpos($path,$lastfolder) + strlen($lastfolder)-1)); #chop the lead slash
        if(strpos($path,'?') != false)
            $path = substr($path, 0, strpos($path,'?')); //remove php arguments
        log_error("path_exp:".$path);
    } 
    else
		report_problem("No path found", 404);
    
	$path = substr($path, 1); #chop the lead slash
	log_error("start request_____" . $path); 
    // ensure that we got a valid request
    if ( !$path ) 
        report_problem("Invalid request, this was not a firefox sync request!", 400);

    // split path into parts and make sure that all values are properly initialized
    list($version, $username, $function, $collection, $id) = array_pad(explode('/', $path.'///'), 5, '');
    
    if($version == 'user' || $version == 'misc')
    {
        //asking for userApi -> user.php
        $include = true;
        require 'user.php';
        exit(); // should not get here, but how knows
    }

    header("Content-type: application/json"); 
    
	if ($version != '1.0' && $version != '1.1')
		report_problem('Function not found', 404);

	if ($function != "info" && $function != "storage")
		report_problem(WEAVE_ERROR_FUNCTION_NOT_SUPPORTED, 400);

	if (!validate_username($username))
		report_problem(WEAVE_ERROR_INVALID_USERNAME, 400);

	#only a delete has meaning without a collection
	if ($collection)
	{
		if (!validate_collection($collection))
			report_problem(WEAVE_ERROR_INVALID_COLLECTION, 400);
	}
	else if ($_SERVER['REQUEST_METHOD'] != 'DELETE')
		report_problem(WEAVE_ERROR_INVALID_PROTOCOL, 400);


	#quick check to make sure that any non-storage function calls are just using GET
	if ($function != 'storage' && $_SERVER['REQUEST_METHOD'] != 'GET')
		report_problem(WEAVE_ERROR_INVALID_PROTOCOL, 400);



	#user passes preliminaries, connections made, onto actually getting the data
	try
	{
		$db = new WeaveStorage($username);

		#Auth the user
		verify_user($username, $db);

		#user passes preliminaries, connections made, onto actually getting the data
		if ($_SERVER['REQUEST_METHOD'] == 'GET')
		{
			if ($function == 'info')
			{
				switch ($collection)
				{
					case 'quota':
						exit(json_encode(array($db->get_storage_total())));
					case 'collections':
						exit(json_encode($db->get_collection_list_with_timestamps()));
					case 'collection_counts':
						exit(json_encode($db->get_collection_list_with_counts()));
					case 'collection_usage':
						$results = $db->get_collection_storage_totals();
						foreach (array_keys($results) as $collection)
						{
							$results[$collection] = ceil($results[$collection] / 1024); #converting to k from bytes
						}
						exit(json_encode($results));
					default:
						report_problem(WEAVE_ERROR_INVALID_PROTOCOL, 400);
				}
			}
			elseif ($function == 'storage')
			{
                log_error("function storage");
				if ($id) #retrieve a single record
				{
					$wbo = $db->retrieve_objects($collection, $id, 1); #get the full contents of one record
					if (count($wbo) > 0)
					{
						$item = array_shift($wbo);
						echo $item->json();
					}
					else
						report_problem("record not found", 404);
				}
				else #retrieve a batch of records. Sadly, due to potential record sizes, have the storage object stream the output...
				{
                    log_error("retrieve a batch");
					$full = array_key_exists('full', $_GET) && $_GET['full'];

					$outputter = new WBOJsonOutput($full);

					$params = validate_search_params();

					$ids = $db->retrieve_objects($collection, null, $full, $outputter,
								$params['parentid'], $params['predecessorid'],
								$params['newer'], $params['older'],
								$params['sort'],
								$params['limit'], $params['offset'],
								$params['ids'],
								$params['index_above'], $params['index_below'], $params['depth']
								);
				}
			}
		}
		else if ($_SERVER['REQUEST_METHOD'] == 'PUT') #add a single record to the server
		{
			$wbo = new wbo();
			if (!$wbo->extract_json(get_json()))
				report_problem(WEAVE_ERROR_JSON_PARSE, 400);

			check_quota($db);
			check_timestamp($collection, $db);

			#use the url if the json object doesn't have an id
			if (!$wbo->id() && $id) { $wbo->id($id); }

			$wbo->collection($collection);
			$wbo->modified($server_time); #current microtime

			if ($wbo->validate())
			{
				#if there's no payload (as opposed to blank), then update the metadata
				if ($wbo->payload_exists())
					$db->store_object($wbo);
				else
					$db->update_object($wbo);
			}
			else
			{
				report_problem(WEAVE_ERROR_INVALID_WBO, 400);
			}
			echo json_encode($server_time);
		}
		else if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			$json = get_json();

			check_quota($db);
			check_timestamp($collection, $db);

			$success_ids = array();
			$failed_ids = array();

			$db->begin_transaction();

			foreach ($json as $wbo_data)
			{
				$wbo = new wbo();

				if (!$wbo->extract_json($wbo_data))
				{
					$failed_ids[$wbo->id()] = $wbo->get_error();
					continue;
				}

				$wbo->collection($collection);
				$wbo->modified($server_time);


				if ($wbo->validate())
				{
					#if there's no payload (as opposed to blank), then update the metadata
					if ($wbo->payload_exists())
					{
						$db->store_object($wbo);
					}
					else
					{
						$db->update_object($wbo);
					}
					$success_ids[] = $wbo->id();
				}
				else
				{
					$failed_ids[$wbo->id()] = $wbo->get_error();
				}
			}
			$db->commit_transaction();

			echo json_encode(array('success' => $success_ids, 'failed' => $failed_ids));
		}
		else if ($_SERVER['REQUEST_METHOD'] == 'DELETE')
		{
			check_timestamp($collection, $db);

			if ($id)
			{
				$db->delete_object($collection, $id);
			}
			else if ($collection)
			{
				$params = validate_search_params();

				$db->delete_objects($collection, null,
								$params['parentid'], $params['predecessorid'],
								$params['newer'], $params['older'],
								$params['sort'],
								$params['limit'], $params['offset'],
								$params['ids'],
								$params['index_above'], $params['index_below']
							);
			}
            else if($function == 'storage') // ich vermute mal storage reinigen
            {
                if (!array_key_exists('HTTP_X_CONFIRM_DELETE', $_SERVER))
                     report_problem(WEAVE_ERROR_NO_OVERWRITE, 412);
                $db->delete_storage($username);
            }
			else
			{ 
				if (!array_key_exists('HTTP_X_CONFIRM_DELETE', $_SERVER))
					report_problem(WEAVE_ERROR_NO_OVERWRITE, 412);
                log_error("delete "."Server ".print_r( $_SERVER, true));
				$db->delete_user($username);
			}

			echo json_encode($server_time);

		}
		else
		{
			#bad protocol. There are protocols left? HEAD, I guess.
			report_problem(1, 400);
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
