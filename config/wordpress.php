<?php

return [
    /*
     * Outbound HTTP timeout in seconds for WordPress requests (publishing).
     */
    'timeout' => (int) env('WORDPRESS_API_TIMEOUT', 30),

    /*
     * REST path of the plugin's publish endpoint, appended to the (possibly
     * subdirectory) site URL. The full request path — including the
     * subdirectory — is what gets signed and what the plugin verifies.
     */
    'publish_path' => '/wp-json/autoblogix/v1/publish',
];
