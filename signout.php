<?php

require_once 'AppDotNet.php';

$app = new AppDotNet();

// log out user
$code = $app->deleteSession();

// redirect user after logging out
header('Location: index.php');

?>
