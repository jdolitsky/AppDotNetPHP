<?php
/**
 * EZAppDotNet.php
 * Class for easy web development
 * https://github.com/jdolitsky/AppDotNetPHP
 *
 * This class does as much of the grunt work as possible in helping you to
 * access the App.net API. In theory you don't need to know anything about
 * oAuth, tokens, or all the ugly details of how it works, it should "just
 * work". 
 *
 * Note this class assumes you're running a web site, and you'll be 
 * accessing it via a web browser (it expects to be able to do things like
 * cookies and sessions). If you're not using a web browser in your App.net
 * application, or you want more fine grained control over what's being
 * done for you, use the included AppDotNet class, which does much
 * less automatically.
 */

require_once 'EZsettings.php';
require_once 'AppDotNet.php';

// comment these two lines out in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// comment this out if session is started elsewhere
session_start();

class EZAppDotNet extends AppDotNet {

	public function __construct($clientId=null,$clientSecret=null) {
		global $app_clientId,$app_clientSecret;

		// if client id wasn't passed, and it's in the settings.php file, use it from there
		if (!$clientId && isset($app_clientId)) {

			// if it's still the default, warn them
			if ($app_clientId == 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX') {
				throw new AppDotNetException('You must change the values defined in EZsettings.php');
			}

			$clientId = $app_clientId;
			$clientSecret = $app_clientSecret;
		}

		// call the parent with the variables we have
		parent::__construct($clientId,$clientSecret,$redirectUri,$scope);
	}

	public function getAuthUrl($redirectUri=null,$scope=null) {
		global $app_redirectUri,$app_scope;
		
		if (is_null($redirectUri)) {
			$redirectUri = $app_redirectUri;
		}
		if (is_null($scope)) {
			$scope = $app_scope;
		}
		return parent::getAuthUrl();
	}

	// user login
	public function setSession($cookie=0) {
		// try and set the token the original way (eg: if they're logging in)
		$token = $this->getAccessToken();

		// if that didn't work, check to see if there's an existing token stored somewhere
		if (!$token) {
			$token = $this->getSession();
		}

		$_SESSION['AppDotNetPHPAccessToken']=$token;

		// if they want to stay logged in via a cookie, set the cookie
		if ($token && $cookie) {
			$cookie_lifetime = time()+(60*60*24*7);
			setcookie('AppDotNetPHPAccessToken',$token,$cookie_lifetime);
		}

		return $token;
	}

	// check if user is logged in
	public function getSession() {

		// first check for cookie
		if (isset($_COOKIE['AppDotNetPHPAccessToken']) && $_COOKIE['AppDotNetPHPAccessToken'] != 'expired') {
			$this->setAccessToken($_COOKIE['AppDotNetPHPAccessToken']);
			return $_COOKIE['AppDotNetPHPAccessToken'];
		}

		// else check the session for the token (from a previous page load)
		else if (isset($_SESSION['AppDotNetPHPAccessToken'])) {
			$this->setAccessToken($_SESSION['AppDotNetPHPAccessToken']);
			return $_SESSION['AppDotNetPHPAccessToken'];
		}

		// whatever we found (even if it's nothing), return it
		return $this->getAccessToken();
	}

	// log the user out
	public function deleteSession() {
		// clear the session
		unset($_SESSION['AppDotNetPHPAccessToken']);

		// unset the cookie
		setcookie('AppDotNetPHPAccessToken', null, 1);

		// clear the access token
		$this->setAccessToken(null);

		// done!
		return true;
	}

}
