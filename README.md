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
A good example of how to sign in, sign out, and retrieve data can be found in index.php

Here is a simple example:
<pre>

require_once 'AppDotNet.php';

$app = new AppDotNet();

// check that the user is signed in
if ($app->getSession()) {

  // get the current user as JSON
	$data = $app->getUser();

	// accessing the user's name
	echo 'Welcome '.$data['name'];

// otherwise prompt to sign in
} else {

	$url = $app->getAuthUrl();
	echo '<a href="'.$url.'"><h2>Sign in using App.net</h2></a>';

}

require ('AppDotNet.php');

$app = new AppDotNet();

// Retrieve a Stream of all public Posts on App.net
$result = $app->getPublicPosts();

$code = $result['code'];
$response = $result['res'];
</pre>