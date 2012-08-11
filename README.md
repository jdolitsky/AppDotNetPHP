AppDotNetPHP
============

PHP library for the App.net Stream API

More info on the App.net Stream API here: https://github.com/appdotnet/api-spec

NOTE:<br>
The Stream API is currently under development. This library will be rapidly changing in accordance with changes made in the API.

Setup:
--------

Usage:
--------
Examples of how to use the library can be found in index.php, callback.php, and signout.php

Here is a simple example of sign-in and data retieval:
<pre>
require_once 'AppDotNet.php';

$app = new AppDotNet();

// check that the user is signed in
if ($app->getSession()) {

	// get the current user as JSON
	$data = $app->getUser();

	// accessing the user's username
	echo 'Welcome '.$data['username'];

// if not, redirect to sign in
} else {

	$url = $app->getAuthUrl();
	header('Loacation: '.$url);
	
}
</pre>