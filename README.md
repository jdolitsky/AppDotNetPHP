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
* <a href="https://alpha.app.net/harold" target="_blank">@harold</a>
* <a href="https://alpha.app.net/hxf148" target="_blank">@hxf148</a>
* <a href="https://alpha.app.net/edent" target="_blank">@edent</a>
* <a href="https://alpha.app.net/cdn" target="_blank">@cdn</a>
* <a href="https://alpha.app.net/ryantharp" target="_blank">@ryantharp</a>

WARNING:
---------
This version breaks a lot of backward compatibility with the previous version, in order to be more flexible with the rapidly evolving API. YOU WILL HAVE TO MAKE CHANGES TO YOUR CODE WHEN YOU UPGRADE.

Usage:
--------
###EZAppDotNet
If you are planning to design an app for viewing within a browser that requires a login screen etc, this is a great place to start. This aims to hide all the nasty authentication stuff from the average developer. It is also recommended that you start here if you have never worked with OAuth and/or APIs before.

```php
<?php

require_once 'EZAppDotNet.php';

$app = new EZAppDotNet();

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
To view a full example in action, you should copy the project files into your webroot directory. Edit the values in **EZsettings.php** to reflect the ones for your app (to make things easy, change the Callback URL within your app.net developers console to http://localhost/ez-example/callback.php). Add or remove values from the $app_scope array to change the permissions your app will have with the authenticated user. Travel to http://localhost/ez-example/ and click 'Sign in with App.net'.

###AppDotNet
Use this class if you need more control of your application (such as running a command line process) or are integrating your code with an existing application that handles sessions/cookies in a different way.

First construct your authentication url.
```php
<?php

require_once 'AppDotNet.php';

// change these to your app's values
$clientId     = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$clientSecret = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

// construct the AppDotNet object
$app = new AppDotNet($clientId,$clientSecret);

$redirectUri  = 'http://localhost/callback.php';
$scope        =  array('stream','email','write_post','follow','messages','update_profile','files','export');

// create an authentication Url
$url = $app->getAuthUrl($redirectUri,$scope);

?>
```
Once the user has authenticated the app, grab the token in the callback script, and get information about the user.
```php
<?php
require_once 'AppDotNet.php';
$app = new AppDotNet($clientId,$clientSecret);

// get the token returned by App.net
// (this also sets the token)
$token = $app->getAccessToken($redirectUri);

// get info about the user
$user = $app->getUser();

// get the unique user id
$userId = $user['id'];

?>
```
Save the token and user id in a database or elsewhere, then make API calls in future scripts after setting the token.
```php
<?php

$app->setAccessToken($token);

// post on behalf of the user w/ that token
$app->createPost('Hello world');

?>
```

To consume the stream, try something like:
```php
<?php

require_once 'AppDotNet.php';
$app = new AppDotNet($clientId,$clientSecret);

// You need an app token to consume the stream, get the token returned by App.net
// (this also sets the token)
$token = $app->getAppAccessToken();

// create a stream
// if you already have a stream you can skip this step
// this stream is going to consume posts and stars (but not follows)
$stream = $app->createStream(array('post','star','user_follow','stream_marker','message','channel','channel_subscription','mute','token','file'));
// you might want to save $stream['endpoint'] or $stream['id'] for later so
// you don't have to re-create the stream

// we need to create a callback function that will do something with posts/stars
// when they're received from the stream. This function should accept one single
// parameter that will be the php object containing the meta / data for the event.
function handleEvent($event) {
	switch ($event['meta']['type']) {
		case 'post':
			print "Handle a post type\n";
			break;
		case 'star':
			print "Handle a star type\n";
			break;
	}
}

// register that function as the stream handler
$app->registerStreamFunction('handleEvent');

// open the stream for reading
$app->openStream($stream['endpoint']);

// now we want to process the stream. We have two options. If all we're doing
// in this script is processing the stream, we can just call:
// $app->processStreamForever();
// otherwise you can create a loop, and call $app->processStream($milliseconds)
// intermittently, like:
while (true) {
	print "hello, I'm going to do some other non-streaming things here...\n";
	// now we're going to process the stream for awhile (3 seconds)
	$app->processStream(3000000);
	// then do something else...
}
?>
```