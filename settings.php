<?php
    //you can disable registration to the firefox sync server here,
    // by setting ENABLE_REGISTER to false
    //
    //
    //define("ENABLE_REGISTER",false);
    define("ENABLE_REGISTER", true);


    //pleas set the URL where firefox clients find the root of 
    // firefox sync server
    // this should end with a /
    //
    define("FSYNCMS_ROOT","https://DOMAIN.de/Folder_und_ggf_/index.php/");

    //MYSQL Params
    define("MYSQL_ENABLE", false);
    define("MYSQL_HOST","localhost");
    define("MYSQL_DB","databaseName");
    define("MYSQL_USER", "databaseUserName");
    define("MYSQL_PASSWORD", "databaseUserPW");

?>
