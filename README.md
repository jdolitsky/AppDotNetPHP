AppDotNetPHP
============

PHP library for the App.net Stream API

More info on the App.net Stream API here: https://github.com/appdotnet/api-spec

NOTE:<br>
The Stream API is currently under development. This library will be rapidly changing in accordance with changes made in the API.

Setup:
--------
Open up AppDotNet.php for editing.

You will need to change the values for the following:
<ol>
<ul>Client ID</ul>
<ul>Client Secret</ul>
<ul>Callback URL</ul>
<ul>Scope</ul>
</ol>

<pre>
// 1.) Enter your Client ID
var $_clientId = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

// 2.) Enter your Client Secret
var $_clientSecret = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

// 3.) Enter your Callback URL
var $_redirectUri = 'http://your-website.com/callback.php';

// 4.) Add or remove scopes
var $_scope = array(
	'stream','email','write_post','follow','messages','export'
);
</pre>

Usage:
--------
Examples of how to use the library can be found in <b>index.php</b>, <b>callback.php</b>, and <b>signout.php</b>

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