<?php

require_once('settings.php');
require_once 'AppDotNet.php';

$app = new AppDotNet($clientId,$clientSecret,$redirect);

// if they have the cookie set, use that instead of asking them to login
if (isset($_COOKIE['ADNAuth']) && $_COOKIE['ADNAuth']) {
	$app->setAccessToken($_COOKIE['ADNAuth']);
}

// else if they're returning from the app.net auth page, tokenize them!
elseif ($app->getSession()) {
	// and store it in a cookie for future use
	// this sets a cookie that will last for 7 days
	setcookie('ADNAuth',$app->getAccessToken(),time()+(60*60*24*7));
}

// else they need to visit the auth url to grant us access
else {
	// we'll handle this below, so they can see the help text before clicking the url
}

?>
<html>
<head>
	<title>Remember me example</title>
</head>
<body>

<h1>Remember me example</h1>

<p>This is an example of "remember me" for oAuth in general, but AppDotNetPHP specifically.</p>

<?php

// prove we're logged in by showing their avatar and username
if ($app->getAccessToken()) {
	$user = $app->getUser();
	print '<p>You\'re logged in as <img src="'.htmlspecialchars($user['avatar_image']['url']).'" width="64" height="64" /> '.htmlspecialchars($user['username']).'</p>';
}

// if we have no auth token for them at all, then offer to log them in
else {
	print '<p>To get started, please <a href="'.htmlspecialchars($app->getAuthUrl()).'">login with app.net</a>.</p>';
}

?>

<p>Once you've logged in above your auth token will be stored in a cookie that's a 
	little more persistent than a session. To demonstrate this, log in above then
	close your browser / destroy your session and re-visit this page. You'll should
	still be logged in, without having to visit the app.net auth page again.</p>

</body>
</html>
