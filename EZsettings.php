<?php

// change these values to your own in order to use EZAppDotNet
$app_clientId     = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$app_clientSecret = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

// this must be one of the URLs defined in your App.net application settings
$app_redirectUri  = 'http://localhost/AppDotNetPHP/ez-example/callback.php';

// An array of permissions you're requesting from the user.
// As a general rule you should only request permissions you need for your app.
// By default all permissions are commented out, meaning you'll have access
// to their basic profile only. Uncomment the ones you need.
$app_scope        =  array(
	// 'stream', // Read the user's personalized stream
	// 'email', // Access the user's email address
	// 'write_post', // Post on behalf of the user
	// 'follow', // Follow and unfollow other users
	// 'messages', // Access the user's private messages
	// 'export', // Export all user data (shows a warning)
);
