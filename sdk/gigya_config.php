<?php
/*
 * local API settings constants
 */
define("API_KEY","");
define("API_DOMAIN","us1.gigya.com");
define("APP_KEY","");

// set to "file" if app secret is saved in file, or "env", if app secret is set as server env var:
define("KEY_SAVE_TYPE", "file"); // "file" / "env" 
    // if key location is et to "env", set an environment variable with the name: GIGYAIM_KEK
    // if KEY_LOCATION is set as "file", specify the path that you have located your key file at:
    define("KEY_PATH", $_SERVER["DOCUMENT_ROOT"] . "/../gig_key.txt"); // leave value blank if not used (if KEY_LOCATION is set to "env")

// Debug mode:
define("GIGYA_DEBUG", true);
// *Gigya module will still output runtime errors to gigya.log with DEBUG mode off.