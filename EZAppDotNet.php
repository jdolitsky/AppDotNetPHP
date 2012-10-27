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

// comment these two lines out in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'EZsettings.php';
require_once 'AppDotNet.php';

// comment this out if session is started elsewhere
session_start();

class EZAppDotNet extends AppDotNet {

	private $_callbacks = array();
	private $_autoShutdownStreams = array();

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
		parent::__construct($clientId,$clientSecret);

		// set up ez streaming
		$this->registerStreamFunction(array($this,'streamEZCallback'));

		// make sure we cleanup/destroy any streams when we exit
		register_shutdown_function(array($this,'stopStreaming'));
	}

	public function getAuthUrl($redirectUri=null,$scope=null) {
		global $app_redirectUri,$app_scope;
		
		if (is_null($redirectUri)) {
			$redirectUri = $app_redirectUri;
		}
		if (is_null($scope)) {
			$scope = $app_scope;
		}
		return parent::getAuthUrl($redirectUri,$scope);
	}

	// user login
	public function setSession($cookie=0,$callback=null) {

		if (!isset($callback)) {
			global $app_redirectUri;
			$cb=$app_redirectUri;
		} else {
			$cb=$callback;
		}

		// try and set the token the original way (eg: if they're logging in)
		$token = $this->getAccessToken($cb);

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

		return false;
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

	/**
	 * Registers a callback function to be called whenever an event of a certain
	 * type is received from the app.net streaming API. Your function will recieve
	 * a PHP associative array containing an app.net object. You must register at
	 * least one callback function before starting to stream (otherwise your data
	 * would simply be discarded). You can register multiple event types and even 
	 * multiple functions per event (just call this method as many times as needed).
	 * If you register multiple functions for a single event, each will be called
	 * every time an event of that type is received.
	 *
	 * Note you should not be doing any significant processing in your callback
	 * functions. Doing so could cause your scripts to fall behind the stream and
	 * risk getting disconnected. Ideally your callback functions should simply
	 * drop the data into a file or database to be collected and processed by
	 * another program.
	 * @param string $type The type of even your callback would like to recieve.
	 * At time of writing the possible options are 'post', 'star', 'user_follow'.
	 */
	public function registerStreamCallback($type,$callback) {
		switch ($type) {
			case 'post':
			case 'star':
			case 'user_follow':
				if (!array_key_exists($type,$this->_callbacks)) {
					$this->_callbacks[$type] = array();
				}
				$this->_callbacks[$type][] = $callback;
				return true;
				break;
			default:
				throw new AppDotNetException('Unknown callback type: '.$type);
		}
	}

	/**
	 * This is the easy way to start streaming. Register some callback functions
	 * using registerCallback(), then call startStreaming(). Every time the stream
	 * gets sent a type of object you have a callback for, your callback function(s)
	 * will be called with the proper data. When your script exits the streams will
	 * be cleaned up (deleted). 
	 * 
	 * Do not use this method if you want to spread out streams across multiple
	 * processes or multiple servers, since the first script that exits/crashes will
	 * delete the streams for everyone else. Instead use createStream() and openStream().
	 * @return true
	 * @see AppDotNetStream::stopStreaming()
	 * @see AppDotNetStream::createStream()
	 * @see AppDotNetStream::openStream()
	 */
	public function startStreaming() {
		// only listen for object types that we have registered callbacks for
		if (!$this->_callbacks) {
			throw new AppDotNetException('You must register at least one callback function before calling startStreaming');
		}
		// if there's already a stream running, don't allow another
		if ($this->_currentStream) {
			throw new AppDotNetException('There is already a stream being consumed, only one stream can be consumed per AppDotNetStream instance');
		}
		$stream = $this->createStream(array_keys($this->_callbacks));
		// when the script exits, delete this stream (if it's still around)
		$this->_autoShutdownStreams[] = $response['id'];
		// start consuming
		$this->openStream($response['id']);
		return true;
	}

	/**
	 * This is the easy way to stop streaming and cleans up the no longer needed stream. 
	 * This method will be called automatically if you started streaming using 
	 * startStreaming().  
	 * 
	 * Do not use this method if you want to spread out streams across multiple
	 * processes or multiple servers, since it will delete the streams for everyone 
	 * else. Instead use closeStream().
	 * @return true
	 * @see AppDotNetStream::startStreaming()
	 * @see AppDotNetStream::deleteStream()
	 * @see AppDotNetStream::closeStream()
	 */
	public function stopStreaming() {
		$this->closeStream();
		// delete any auto streams
		foreach ($this->_autoShutdownStreams as $streamId) {
			$this->deleteStream($streamId);
		}
		return true;
	}

	/**
	 * Internal function used to make your streaming easier. I hope.
	 */
	protected function streamEZCallback($type,$data) {
		// if there are defined callbacks for this object type, then...
		if (array_key_exists($type,$this->_callbacks)) {
			// loop through the callbacks notifying each one in turn
			foreach ($this->_callbacks[$type] as $callback) {
				call_user_func($callback,$data);
			}
		}
	}

}