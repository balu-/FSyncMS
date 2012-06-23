FSyncMS
=======

PHP Sync Server für Firefox Sync
Eine erweiterung des Weave-Minimal Server (dessen Support leider eingestellt wurde).

Die derzeit aktuelle Versionen,
sowie alte Versionen und Anleitungen sind hier:

https://www.ohnekontur.de/category/technik/sync/fsyncms/

zu finden.
Weitere Erweiterungen sind in Planung.
Stay tuned.


Visit http://www.ohnekontur.de/2011/07/24/how-to-install-fsyncms-firefox-sync-eigener-server/ for install instructions
Visit http://www.ohnekontur.de for the newest version

FSyncMS v011
======
Added dedicated setup script, which will create the database and the config file: settings.php

If you want to create it by your own, just generate the settings.php with the following content

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


FSyncMS v010
======
MYSQL Support

FSyncMS v 09
======
Change Password now supported 
working with firefox 12 (and lower)

Changelog:
Added change Password feature

FSyncMS v 08
======
Should be working with firefox 11 and lower (tested with 11)

Changelog:
Fixed user registration process,
fixed some delete problems
