<?php

require_once 'AppDotNet.php';

$app = new AppDotNet();

// log in user
$code = $app->setSession();

// redirect user after logging in
header('Location: index.php');

?>
