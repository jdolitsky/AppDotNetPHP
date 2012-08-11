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
A good example of sign in, sign out, and data retrieval can be found in index.php

Here is a simple example:
<pre>
require ('AppDotNet.php');

$app = new AppDotNet();

// Retrieve a Stream of all public Posts on App.net
$result = $app->getPublicPosts();

$code = $result['code'];
$response = $result['res'];
</pre>