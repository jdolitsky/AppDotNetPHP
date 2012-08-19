<?php

// testing
require_once('settings.php');

require_once 'AppDotNet.php';

$app = new AppDotNet($clientId,$clientSecret,$redirect);

// log in user
$code = $app->setSession();

// redirect user after logging in
header('Location: index.php');

?>
