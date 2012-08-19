AppDotNetPHP
============

PHP library for the App.net Stream API

More info on the App.net Stream API <a target="_blank" href="https://github.com/appdotnet/api-spec">here</a>

Find more App.net code libraries and examples <a target="_blank" href="https://github.com/appdotnet/api-spec/wiki/Directory-of-third-party-devs-and-apps">here</a>

Find me on App.net <a target="_blank" href="https://alpha.app.net/jdolitsky">here</a>

NOTE:<br>
The Stream API is currently under development. This library will be rapidly changing in accordance with changes made in the API.

**Contributors:**
* <a href="https://github.com/ravisorg" target="_blank">@ravisorg</a>
* <a href="https://github.com/wpstudio" target="_blank">@wpstudio</a>

Usage:
--------
Good examples of how to use the library can be found in <b>index.php</b>, <b>callback.php</b>, and <b>signout.php</b>

Here is a simple example of signing in, posting, and data retrieval:
```php
<?php

require_once 'AppDotNet.php';

$app = new AppDotNet();

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

Setup:
--------
Open up <b>AppDotNet.php</b> for editing

You will need to change the values for the following between lines 24-36:
<ol>
<ul>Client ID</ul>
<ul>Client Secret</ul>
<ul>Callback URL</ul>
<ul>Scope</ul>
</ol>