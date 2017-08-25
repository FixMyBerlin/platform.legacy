<?php

require_once 'google-api-php-client/vendor/autoload.php';
$client = new Google_Client();
$client->setAuthConfig(DRUPAL_ROOT.'/../FixMyBerlin-9c2989a86d05.json');
$client->setApplicationName("FixMyBerlin Sheets API");
$client->useApplicationDefaultCredentials();
$client->addScope('https://spreadsheets.google.com/feeds');
$service = new Google_Service_Sheets($client);
