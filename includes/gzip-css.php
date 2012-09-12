<?php

        // initialize ob_gzhandler function to send and compress data
        ob_start( "ob_gzhandler" );

        // send the requisite header information and character set
        header( "Content-Type: text/css; charset=utf-8" );

        // check cached credentials and reprocess accordingly
        header( "Cache-Control: must-revalidate" );

        // set variable for duration of cached content
        $offset = 60 * 60;

        // set variable specifying format of expiration header
        $expire = "Expires: " . gmdate ("D, d M Y H:i:s", time() + $offset) . " GMT";

        // send cache expiration header to the client broswer
        header( $expire );

?>
