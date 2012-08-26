<?php

require_once '../EZAppDotNet.php';

$app = new EZAppDotNet();

// log out user
$app->deleteSession();

// redirect user after logging out
header('Location: index.php');

?>
