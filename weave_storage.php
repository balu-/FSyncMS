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
# The Original Code is Weave Basic Object Server
#
# The Initial Developer of the Original Code is
# Mozilla Labs.
# Portions created by the Initial Developer are Copyright (C) 2008
# the Initial Developer. All Rights Reserved.
#
# Contributor(s):
#	Toby Elliott (telliott@mozilla.com)
#   balu
#   Daniel Triendl <daniel@pew.cc>
#   Moonchild <moonchild@palemoon.org>
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

require_once 'weave_basic_object.php';
require_once 'weave_utils.php';
require_once 'settings.php';

class WeaveStorage
{
    private $_username;
    private $_dbh;

    function __construct($username) 
    {

        $this->_username = $username;

        log_error("Initalizing DB connecion!");

        try 
        {
            if ( ! MYSQL_ENABLE ) 
            {
                $path = explode('/', $_SERVER['SCRIPT_FILENAME']);
                $db_name = SQLITE_FILE;
                array_pop($path);
                array_push($path, $db_name);
                $db_name = implode('/', $path);

                if ( ! file_exists($db_name) ) 
                {
                    log_error("The required sqllite database is not present! $db_name");
                }

                log_error("Starting SQLite connection");
                $this->_dbh = new PDO('sqlite:' . $db_name);
                $this->_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } 
            else if ( MYSQL_ENABLE ) 
            {
                log_error("Starting MySQL connection");
                $this->_dbh = new PDO("mysql:host=". MYSQL_HOST .";dbname=". MYSQL_DB, MYSQL_USER, MYSQL_PASSWORD);
            }

        } 

        catch( PDOException $exception ) 
        {
            log_error("database unavailable " . $exception->getMessage());
            throw new Exception("Database unavailable " . $exception->getMessage() , 503);
        }

    }

    function get_connection()
    {
        return $this->_dbh;
    }

    function begin_transaction()
    {
        try
        {
            $this->_dbh->beginTransaction();
        }
        catch( PDOException $exception )
        {
            error_log("begin_transaction: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }
        return 1;
    }

    function commit_transaction()
    {
        $this->_dbh->commit();
        return 1;
    }

    function get_max_timestamp($collection)
    {
        if (!$collection)
        {
            return 0;
        }

        try
        {
            $select_stmt = 'select max(modified) from wbo where username = :username and collection = :collection';
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->bindParam(':username', $this->_username);
            $sth->bindParam(':collection', $collection);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            error_log("get_max_timestamp: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }

        $result = $sth->fetchColumn();
        return round((float)$result, 2);
    }

    function get_collection_list()
    {
        try
        {
            $select_stmt = 'select distinct(collection) from wbo where username = :username';
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->bindParam(':username', $this->_username);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            error_log("get_collection_list: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }


        $collections = array();
        while ($result = $sth->fetchColumn())
        {
            $collections[] = $result;
        }

        return $collections;
    }


    function get_collection_list_with_timestamps()
    {
        try
        {
            $select_stmt = 'select collection, max(modified) as timestamp from wbo where username = :username group by collection';
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->bindParam(':username', $this->_username);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            error_log("get_collection_list: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }

        $collections = array();
        while ($result = $sth->fetch(PDO::FETCH_NUM))
        {
            $collections[$result[0]] = (float)$result[1];
        }

        return $collections;
    }

    function get_collection_list_with_counts()
    {
        try
        {
            $select_stmt = 'select collection, count(*) as ct from wbo where username = :username group by collection';
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->bindParam(':username', $this->_username);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            error_log("get_collection_list_with_counts: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }


        $collections = array();
        while ($result = $sth->fetch(PDO::FETCH_NUM))
        {
            $collections[$result[0]] = (int)$result[1];
        }

        return $collections;
    }

    function store_object(&$wbo)
    {

        try
        {
            if ( MYSQL_ENABLE )
            { 
                $insert_stmt = 'insert into wbo (username, id, collection, parentid, predecessorid, sortindex, modified, payload, payload_size) 
                                values (:username, :id, :collection, :parentid, :predecessorid, :sortindex, :modified, :payload, :payload_size) 
                                on duplicate key update 
                                username=values(username), id=values(id), collection=values(collection), parentid=values(parentid), 
                                predecessorid=values(predecessorid), sortindex=values(sortindex), modified=values(modified), payload=values(payload), 
                                payload_size=values(payload_size)';
            } 
            else 
            {
                $insert_stmt = 'replace into wbo (username, id, collection, parentid, predecessorid, sortindex, modified, payload, payload_size)
                                values (:username, :id, :collection, :parentid, :predecessorid, :sortindex, :modified, :payload, :payload_size)';
            }
            
            $sth = $this->_dbh->prepare($insert_stmt);

            $username = $this->_username;
            $id = $wbo->id();
            $collection = $wbo->collection();
            $parentid = $wbo->parentid();
            $predecessorid = $wbo->predecessorid();
            $sortindex = $wbo->sortindex();
            $modified = $wbo->modified();
            $payload = $wbo->payload();
            $payload_size = $wbo->payload_size();

            $sth->bindParam(':username', $username);
            $sth->bindParam(':id', $id);
            $sth->bindParam(':collection', $collection);
            $sth->bindParam(':parentid', $parentid);
            $sth->bindParam(':predecessorid', $predecessorid);
            $sth->bindParam(':sortindex', $sortindex);
            $sth->bindParam(':modified', $modified);
            $sth->bindParam(':payload', $payload);
            $sth->bindParam(':payload_size', $payload_size);

            $sth->execute();

        }
        catch( PDOException $exception )
        {
            error_log("store_object: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }
        return 1;
    }


    function update_object(&$wbo)
    {
        $update = "update wbo set ";
        $params = array();
        $update_list = array();

        #make sure we have an id and collection. No point in continuing otherwise
        if (!$wbo->id() || !$wbo->collection())
        {
            error_log('Trying to update without a valid id or collection!');
            return 0;
        }

        if ($wbo->parentid_exists())
        {
            $update_list[] = "parentid = ?";
            $params[] = $wbo->parentid();
        }

        if ($wbo->predecessorid_exists())
        {
            $update_list[] = "predecessorid = ?";
            $params[] = $wbo->predecessorid();
        }

        if ($wbo->sortindex_exists())
        {
            $update_list[] = "sortindex = ?";
            $params[] = $wbo->sortindex();
        }

        if ($wbo->payload_exists())
        {
            $update_list[] = "payload = ?";
            $update_list[] = "payload_size = ?";
            $params[] = $wbo->payload();
            $params[] = $wbo->payload_size();
        }

# Don't modify the timestamp on a non-payload/non-parent change change
        if ($wbo->parentid_exists() || $wbo->payload_exists())
        {
#better make sure we have a modified date. Should have been handled earlier
            if (!$wbo->modified_exists())
            {
                error_log("Called update_object with no defined timestamp. Please check");
                $wbo->modified(microtime(1));
            }
            $update_list[] = "modified = ?";
            $params[] = $wbo->modified();

        }


        if (count($params) == 0)
        {
            return 0;
        }

        $update .= join($update_list, ",");

        $update .= " where username = ? and collection = ? and id = ?";
        $params[] = $this->_username;
        $params[] = $wbo->collection();
        $params[] = $wbo->id();

        try
        {
            $sth = $this->_dbh->prepare($update);
            $sth->execute($params);
        }
        catch( PDOException $exception )
        {
            error_log("update_object: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }
        return 1;
    }

    function delete_object($collection, $id)
    {
        try
        {
            $delete_stmt = 'delete from wbo where username = :username and collection = :collection and id = :id';
            $sth = $this->_dbh->prepare($delete_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->bindParam(':collection', $collection);
            $sth->bindParam(':id', $id);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            error_log("delete_object: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }
        return 1;
    }

    function delete_objects($collection, $id = null, $parentid = null, $predecessorid = null, $newer = null,
            $older = null, $sort = null, $limit = null, $offset = null, $ids = null,
            $index_above = null, $index_below = null)
    {
        $params = array();
        $select_stmt = '';

        if ($limit || $offset || $sort)
        {
#sqlite can't do sort or limit deletes without special compiled versions
#so, we need to grab the set, then delete it manually.

            $params = $this->retrieve_objects($collection, $id, 0, 0, $parentid, $predecessorid, $newer, $older, $sort, $limit, $offset, $ids, $index_above, $index_below);
            if (!count($params))
            {
                return 1; #nothing to delete
            }
            $paramqs = array();
            $select_stmt = "delete from wbo where username = ? and collection = ? and id in (" . join(", ", array_pad($paramqs, count($params), '?')) . ")";
            array_unshift($params, $collection);
            array_unshift($params, $username);
        }
        else
        {

            $select_stmt = "delete from wbo where username = ? and collection = ?";
            $params[] = $this->_username;
            $params[] = $collection;


            if ($id)
            {
                $select_stmt .= " and id = ?";
                $params[] = $id;
            }

            if ($ids && count($ids) > 0)
            {
                $qmarks = array();
                $select_stmt .= " and id in (";
                foreach ($ids as $temp)
                {
                    $params[] = $temp;
                    $qmarks[] = '?';
                }
                $select_stmt .= implode(",", $qmarks);
                $select_stmt .= ')';
            }

            if ($parentid)
            {
                $select_stmt .= " and parentid = ?";
                $params[] = $parentid;
            }

            if ($predecessorid)
            {
                $select_stmt .= " and predecessorid = ?";
                $params[] = $parentid;
            }

            if ($index_above)
            {
                $select_stmt .= " and sortindex > ?";
                $params[] = $parentid;
            }

            if ($index_below)
            {
                $select_stmt .= " and sortindex < ?";
                $params[] = $parentid;
            }

            if ($newer)
            {
                $select_stmt .= " and modified > ?";
                $params[] = $newer;
            }

            if ($older)
            {
                $select_stmt .= " and modified < ?";
                $params[] = $older;
            }

            if ($sort == 'index')
            {
                $select_stmt .= " order by sortindex desc";
            }
            else if ($sort == 'newest')
            {
                $select_stmt .= " order by modified desc";
            }
            else if ($sort == 'oldest')
            {
                $select_stmt .= " order by modified";
            }

        }

        try
        {
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->execute($params);
        }
        catch( PDOException $exception )
        {
            error_log("delete_objects: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }
        return 1;
    }

    function retrieve_object($collection, $id)
    {
        try
        {
            $select_stmt = 'select * from wbo where username = :username and collection = :collection and id = :id';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->bindParam(':collection', $collection);
            $sth->bindParam(':id', $id);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            error_log("retrieve_object: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }

        $result = $sth->fetch(PDO::FETCH_ASSOC);

        $wbo = new wbo();
        $wbo->populate($result);
        return $wbo;
    }

    function retrieve_objects($collection, $id = null, $full = null, $direct_output = null, $parentid = null,
            $predecessorid = null, $newer = null, $older = null, $sort = null,
            $limit = null, $offset = null, $ids = null,
            $index_above = null, $index_below = null)
    {
        $full_list = $full ? '*' : 'id';


        $select_stmt = "select $full_list from wbo where username = ? and collection = ?";
        $params[] = $this->_username;
        $params[] = $collection;


        if ($id)
        {
            $select_stmt .= " and id = ?";
            $params[] = $id;
        }

        if ($ids && count($ids) > 0)
        {
            $qmarks = array();
            $select_stmt .= " and id in (";
            foreach ($ids as $temp)
            {
                $params[] = $temp;
                $qmarks[] = '?';
            }
            $select_stmt .= implode(",", $qmarks);
            $select_stmt .= ')';
        }

        if ($parentid)
        {
            $select_stmt .= " and parentid = ?";
            $params[] = $parentid;
        }


        if ($predecessorid)
        {
            $select_stmt .= " and predecessorid = ?";
            $params[] = $predecessorid;
        }

        if ($index_above)
        {
            $select_stmt .= " and sortindex > ?";
            $params[] = $parentid;
        }

        if ($index_below)
        {
            $select_stmt .= " and sortindex < ?";
            $params[] = $parentid;
        }

        if ($newer)
        {
            $select_stmt .= " and modified > ?";
            $params[] = $newer;
        }

        if ($older)
        {
            $select_stmt .= " and modified < ?";
            $params[] = $older;
        }

        if ($sort == 'index')
        {
            $select_stmt .= " order by sortindex desc";
        }
        else if ($sort == 'newest')
        {
            $select_stmt .= " order by modified desc";
        }
        else if ($sort == 'oldest')
        {
            $select_stmt .= " order by modified";
        }

        if ($limit)
        {
            $select_stmt .= " limit " . intval($limit);
            if ($offset)
            {
                $select_stmt .= " offset " . intval($offset);
            }
        }

        try
        {
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->execute($params);
        }
        catch( PDOException $exception )
        {
            error_log("retrieve_collection: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }

        if ($direct_output)
            return $direct_output->output($sth);

        $ids = array();
        while ($result = $sth->fetch(PDO::FETCH_ASSOC))
        {
            if ($full)
            {
                $wbo = new wbo();
                $wbo->populate($result);
                $ids[] = $wbo;
            }
            else
                $ids[] = $result{'id'};
        }
        return $ids;
    }

    function get_storage_total()
    {
        try
        {
            $select_stmt = 'select round(sum(length(payload))/1024) from wbo where username = :username';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            error_log("get_storage_total: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }

        return (int)$sth->fetchColumn();
    }

    function get_collection_storage_totals()
    {
        try
        {
            $select_stmt = 'select collection, sum(payload_size) from wbo where username = :username group by collection';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            error_log("get_storage_total (" . $this->connection_details_string() . "): " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }
        $results = $sth->fetchAll(PDO::FETCH_NUM);
        $sth->closeCursor();

        $collections = array();
        foreach ($results as $result)
        {
            $collections[$result[0]] = (int)$result[1];
        }
        return $collections;
    }


    function get_user_quota()
    {
        return null;
    }

    function delete_storage($username)
    {
        log_error("delete storage");
        if (!$username)
        {
            throw new Exception("3", 404);
        }
        try
        {
            $delete_stmt = 'delete from wbo where username = :username';
            $sth = $this->_dbh->prepare($delete_stmt);
            $sth->bindParam(':username', $username);
            $sth->execute();
            $sth->closeCursor();
        }
        catch( PDOException $exception )
        { 
            error_log("delete_user: " . $exception->getMessage());
            return 0;
        } 
        return 1;

    }

    function delete_user($username)
    {
        log_error("delete User");
        if (!$username)
        {
            throw new Exception("3", 404);
        }

        try
        {
            $delete_stmt = 'delete from users where username = :username';
            $sth = $this->_dbh->prepare($delete_stmt);
            $sth->bindParam(':username', $username);
            $sth->execute();
            $sth->closeCursor();

            $delete_wbo_stmt = 'delete from wbo where username = :username';
            $sth = $this->_dbh->prepare($delete_wbo_stmt);
            $sth->bindParam(':username', $username);
            $sth->execute();

        }
        catch( PDOException $exception )
        {
            error_log("delete_user: " . $exception->getMessage());
            return 0;
        }
        return 1;
    }

    function create_user($username, $password)
    {
        log_error("Create User - Username: ".$username."|".$password);

        try
        {
            $create_statement = "insert into users values (:username, :md5)";

            $sth = $this->_dbh->prepare($create_statement);
            $hash = WeaveHashFactory::factory();
            $password = $hash->hash($password);
            $sth->bindParam(':username', $username);
            $sth->bindParam(':md5', $password);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            log_error("create_user:" . $exception->getMessage());
            error_log("create_user:" . $exception->getMessage());
            return 0;
        }
        return 1;
    }

    function change_password($hash)
    {
        try
        {
            $update_statement = "update users set md5 = :md5 where username = :username";

            $sth = $this->_dbh->prepare($update_statement);
            $sth->bindParam(':username', $this->_username);
            $sth->bindParam(':md5', $hash);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            log_error("change_password:" . $exception->getMessage());
            return 0;
        }
        return 1;
    }

    #function checks if user exists
    function exists_user()
    {
        try
        {
            $select_stmt = 'select username from users where username = :username';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            error_log("exists_user: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }

        if (!$result = $sth->fetch(PDO::FETCH_ASSOC))
        {
            return null;
        }
        return 1;
    }


    function get_password_hash()
    {
        log_error("auth-user: " . $this->_username);
        try
        {
            $select_stmt = 'select md5 from users where username = :username';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->execute();
        }
        catch( PDOException $exception )
        {
            error_log("get_password_hash: " . $exception->getMessage());
            throw new Exception("Database unavailable", 503);
        }

        $result = $sth->fetchColumn();
        if ($result === FALSE) $result = "";
        
        return $result; 
    }

}


?>
