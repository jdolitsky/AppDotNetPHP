AppDotNetPHP
============

PHP library for the App.net Stream API

More info on the App.net Stream API <a target="_blank" href="https://github.com/appdotnet/api-spec">here</a>

Find more App.net code libraries and examples <a target="_blank" href="https://github.com/appdotnet/api-spec/wiki/Directory-of-third-party-devs-and-apps">here</a>

Sign up for App.net <a target="_blank" href="https://join.app.net/">here</a>

NOTE:<br>
The Stream API is currently under development. This library will be rapidly changing in accordance with changes made in the API.

**Contributors:**
* <a href="https://alpha.app.net/jdolitsky" target="_blank">@jdolitsky</a>
* <a href="https://alpha.app.net/ravisorg" target="_blank">@ravisorg</a>
* <a href="https://github.com/wpstudio" target="_blank">@wpstudio</a>
* <a href="https://alpha.app.net/hxf148" target="_blank">@hxf148</a>

Usage:
--------
Good examples of how to use the library can be found in <b>index.php</b>, <b>callback.php</b>, <b>signout.php, and remember_me.php files. Make sure you update your client id/secret in settings.php before starting.</b>

Here is a simple example of signing in, posting, and data retrieval:
```php
<?php

require_once 'AppDotNet.php';

$app = new AppDotNet('client id','client secret','http://callback/url');

// check that the user is signed in
if ($app->getSession()) {

	// post on behalf of the user
	$app->createPost('Hello world');

	// get the current user as JSON
	$data = $app->getUser();

	// accessing the user's username
	echo 'Welcome '.$data['username'];

// if not, redirect to sign in
} else {

	$url = $app->getAuthUrl();
	header('Location: '.$url);
	
}

?>
```
