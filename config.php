<?php

return array(

    /* -----------------------------------------------------
     * Default API client
     * -------------------------------------------------- */

    'client' => array(
        'id' => '',
        'key' => ''
    ),

    /* -----------------------------------------------------
     * Named API clients
     * -------------------------------------------------- */

    'clients' => array(

        // Example: Usage with routes for multi-client installs
        'client-one' => array(
            'id' => '',
            'key' => ''
        ),
        'client-two' => array(
            'id' => '',
            'key' => ''
        ),
        'client-three' => array(
            'id' => '',
            'key' => ''
        )
    ),


    /* -----------------------------------------------------
     * Route settings
     * -------------------------------------------------- */

    'routes' => array(

        // Default route
        array(
            'request' => array(
                'template' => 'default'
            )
        ),
        // Example: Multi-client routing by host
        array(
            'match' => array(
                'host' => '*.client-one.host.com'
            ),
            'request' => array(
                'client' => 'client-one',
                'template' => 'client-template'
            )
        )
    ),

    /* -----------------------------------------------------
     * Plugin settings
     * -------------------------------------------------- */

    'plugins' => array(

    ),

    /* -----------------------------------------------------
     * Template settings
     * -------------------------------------------------- */

    'templates' => array(
        
    )
);
