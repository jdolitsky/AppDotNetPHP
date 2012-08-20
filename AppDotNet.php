<?php
/*
|
|  AppDotNetPHP: App.net PHP library
|    created by Josh Dolitsky, August 2012
|    https://github.com/jdolitsky/AppDotNetPHP
|
|  For more info on App.net, please visit:
|    https://join.app.net/
|    https://github.com/appdotnet/api-spec
|
*/

// comment these two lines out in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

class AppDotNetException extends Exception {}

class AppDotNet {

	// 1.) Enter your Client ID
	// The new preferred way of doing this is to send this as the first 
	// parameter when constructing your AppDotNet object. 
	// ie: $app = new AppDotNet($clientId,$clientSecret,$redirectUri);
	var $_clientId = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

	// 2.) Enter your Client Secret
	// The new preferred way of doing this is to send this as the second
	// parameter when constructing your AppDotNet object. 
	// ie: $app = new AppDotNet($clientId,$clientSecret,$redirectUri);
	var $_clientSecret = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

	// 3.) Enter your Callback URL
	// The new preferred way of doing this is to send this as the third 
	// parameter when constructing your AppDotNet object. 
	// ie: $app = new AppDotNet($clientId,$clientSecret,$redirectUri);
	var $_redirectUri = 'http://your-website.com/callback.php';
	
	// 4.) Add or remove scopes
	var $_scope = array(
		'stream','email','write_post','follow','messages','export'
	);

	var $_baseUrl = 'https://alpha-api.app.net/stream/0/';
	var $_authUrl = 'https://alpha.app.net/oauth/';

	var $_authSignInUrl;
	var $_authPostParams=array();

	// stores the access token after login
	var $_accessToken = null;

	// The total number of requests you're allowed within the alloted time period
	var $_rateLimit = null;

	// The number of requests you have remaining within the alloted time period
	var $_rateLimitRemaining = null;

	// The number of seconds remaining in the alloted time period
	var $_rateLimitReset = null;

	// constructor
	function AppDotNet($clientId=null,$clientSecret=null,$redirectUri=null) {

		if ($clientId && $clientSecret && $redirectUri) {
			$this->_clientId = $clientId;
			$this->_clientSecret = $clientSecret;
			$this->_redirectUri = $redirectUri;
		}

		$this->_scope = implode('+',$this->_scope);

		$this->_authSignInUrl = $this->_authUrl.'authenticate?client_id='.$this->_clientId
				        .'&response_type=code&redirect_uri='.$this->_redirectUri
				        .'&scope='.$this->_scope;


		$this->_authPostParams = array('client_id'=>$this->_clientId,
						'client_secret'=>$this->_clientSecret,
		      				'grant_type'=>'authorization_code',
						'redirect_uri'=>$this->_redirectUri);
	}

	// returns the authentication URL constructed above
	function getAuthUrl() {
		return $this->_authSignInUrl;
	}

	// user login
	function setSession() {
		if (isset($_GET['code'])) {
			$code = $_GET['code'];
			$this->_authPostParams['code']=$code;
			$res = $this->httpPost($this->_authUrl.'access_token', $this->_authPostParams);
			$this->_accessToken = $res['access_token'];
			$_SESSION['AppDotNetPHPAccessToken']=$this->_accessToken;
			return $this->_accessToken;
		}
		return false;
	}

	// check if user is logged in
	function getSession() {
		// else check the session for the token (from a previous page load)
		if (isset($_SESSION['AppDotNetPHPAccessToken'])) {
			$this->_accessToken = $_SESSION['AppDotNetPHPAccessToken'];
			return $_SESSION['AppDotNetPHPAccessToken'];
		} 
		return false;
	}

	// log the user out
	function deleteSession() {
		unset($_SESSION['AppDotNetPHPAccessToken']);
		$this->_accessToken = null;
		return false;
	}

	// return the access token (eg: for offline storage)
	function getAccessToken() {
		return $this->_accessToken;
	}

	// set the access token (eg: after retrieving it from offline storage)
	function setAccessToken($token) {
		$this->_accessToken = $token;
	}

	// The total number of requests you're allowed within the alloted time period
	function getRateLimit() {
		return $this->_rateLimit;
	}

	// The number of requests you have remaining within the alloted time period
	function getRateLimitRemaining() {
		return $_rateLimitRemaining;
	}

	// The number of seconds remaining in the alloted time period
	// When this time is up you'll have getRateLimit() available again
	function getRateLimitReset() {
		return $_rateLimitReset;
	}

	function parseHeaders($response) {
		// take out the headers
		// set internal variables
		// return the body/content
		$this->rateLimit = null;
		$this->rateLimitRemaining = null;
		$this->rateLimitReset = null;

		list($headers,$content) = explode("\r\n\r\n",$response,2);

		// this is not a good way to parse http headers
		// it will not (for example) take into account multiline headers
		// but what we're looking for is pretty basic, so we can ignore those shortcomings
		$headers = explode("\r\n",$headers);
		foreach ($headers as $header) {
			$header = explode(': ',$header,2);
			if (count($header)<2) {
				continue;
			}
			list($k,$v) = $header;
			switch ($k) {
				case 'X-RateLimit-Remaining':
					$this->rateLimitRemaining = $v;
					break;
				case 'X-RateLimit-Limit':
					$this->rateLimit = $v;
					break; 
				case 'X-RateLimit-Reset':
					$this->rateLimitReset = $v;
					break;

			}
		}
		return $content;
	}

	// function to handle all POST requests
	function httpPost($req, $params=array()) {
		$ch = curl_init($req); 
		curl_setopt($ch, CURLOPT_POST, true);
		if ($this->_accessToken) {
			curl_setopt($ch,CURLOPT_HTTPHEADER,array('Authorization: Bearer '.$this->_accessToken));
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		$qs = http_build_query($params);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $qs);
		$response = curl_exec($ch); 
		curl_close($ch);
		$response = $this->parseHeaders($response);
		$response = json_decode($response,true);
		if (isset($response['error'])) {
			if (is_array($response['error'])) {
				throw new AppDotNetException($response['error']['message'],$response['error']['code']);
			}
			else {
				throw new AppDotNetException($response['error']);
			}
		} else {
			return $response;
		}
	}

	// function to handle all GET requests
	function httpGet($req) {
		$ch = curl_init($req); 
		curl_setopt($ch, CURLOPT_POST, false);
		if ($this->_accessToken) {
			curl_setopt($ch,CURLOPT_HTTPHEADER,array('Authorization: Bearer '.$this->_accessToken));
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		$response = curl_exec($ch); 
		curl_close($ch);
		$response = $this->parseHeaders($response);
		$response = json_decode($response,true);
		if (isset($response['error'])) {
			if (is_array($response['error'])) {
				throw new AppDotNetException($response['error']['message'],$response['error']['code']);
			}
			else {
				throw new AppDotNetException($response['error']);
			}
		} else {
			return $response;
		}
		
	}

	// function to handle all DELETE requests
	function httpDelete($req, $params=array()) {
		$ch = curl_init($req); 
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		if ($this->_accessToken) {
			curl_setopt($ch,CURLOPT_HTTPHEADER,array('Authorization: Bearer '.$this->_accessToken));
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		$qs = http_build_query($params);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $qs);
		$response = curl_exec($ch); 
		curl_close($ch);
		$response = $this->parseHeaders($response);
		$response = json_decode($response,true);
		if (isset($response['error'])) {
			if (is_array($response['error'])) {
				throw new AppDotNetException($response['error']['message'],$response['error']['code']);
			}
			else {
				throw new AppDotNetException($response['error']);
			}
		} else {
			return $response;
		}
	}

	// Return the Filters for the current user.
	function getAllFilters() {
		
		return $this->httpGet($this->_baseUrl.'filters');

	}

	// Create a Filter for the current user.
	function createFilter($name='New filter', $user_ids=array(), $hashtags=array(), 
                                     $link_domains=array(), $mention_user_ids= array()) {

		$params = array('name'=>$name, 'user_ids'=>$user_ids, 'hashtags'=>$hashtags,
				'link_domains'=>$link_domains, 'mention_user_ids'=>$mention_user_ids);
		
		return $this->httpPost($this->_baseUrl.'filters',$params);

	}

	// Returns a specific Filter object.
	function getFilter($filter_id=null) {
		
		return $this->httpGet($this->_baseUrl.'filters/'.$filter_id);

	}

	// Delete a Filter. The Filter must belong to the current User. 
	// It returns the deleted Filter on success.
	function deleteFilter($filter_id=null) {
		
		return $this->httpDelete($this->_baseUrl.'filters');

	}

	// Create a new Post object. Mentions and hashtags will be parsed out of the 
	// post text, as will bare URLs. To create a link in a post without using a 
	// bare URL, include the anchor text in the post's text and include a link 
	// entity in the post creation call.
	function createPost($text=null, $reply_to=null, $annotations=null, $links=null) {

		$params = array('text'=>$text, 'reply_to'=>$reply_to, 
                                'annotations'=>$annotations, 'links'=>$links);

		return $this->httpPost($this->_baseUrl.'posts',$params);

	}

	// Returns a specific Post.
	function getPost($post_id=null) {

		return $this->httpGet($this->_baseUrl.'posts/'.$post_id);

	}

	// Delete a Post. The current user must be the same user who created the Post. 
	// It returns the deleted Post on success.
	function deletePost($post_id=null) {

		return $this->httpDelete($this->_baseUrl.'posts/'.$post_id);

	}

	// Retrieve the Posts that are 'in reply to' a specific Post.
	function getPostReplies($post_id=null,$count=20,$before_id=null,$since_id=null) {
	
		return $this->httpGet($this->_baseUrl.'posts/'.$post_id.'/replies?count='
					.$count.'&before_id='.$before_id.'&since_id='.$since_id);
	
	}
	
	// Get the most recent Posts created by a specific User in reverse 
	// chronological order.
	function getUserPosts($user_id='me',$count=20,$before_id=null,$since_id=null) {
	
		return $this->httpGet($this->_baseUrl.'users/'.$user_id.'/posts?count='
					.$count.'&before_id='.$before_id.'&since_id='.$since_id);
	
	}
	
	// Get the most recent Posts mentioning by a specific User in reverse 
	// chronological order.
	function getUserMentions($user_id='me',$count=20,$before_id=null,$since_id=null) {
	
		return $this->httpGet($this->_baseUrl.'users/'.$user_id.'/mentions?count='
					.$count.'&before_id='.$before_id.'&since_id='.$since_id);
	
	}

	// Return the 20 most recent Posts from the current User and 
	// the Users they follow.
	function getUserStream($user_id='me',$count=20,$before_id=null,$since_id=null) {
		return $this->httpGet($this->_baseUrl.'posts/stream/global?count='
					.$count.'&before_id='.$before_id.'&since_id='.$since_id);
	
	}

	// Returns a specific User object.
	function getUser($user_id='me') {

		return $this->httpGet($this->_baseUrl.'users/'.$user_id);

	}

	// Returns the User object of the user being followed.
	function followUser($user_id=null) {

		return $this->httpPost($this->_baseUrl.'users/'.$user_id.'/follow');

	}

	// Returns the User object of the user being unfollowed.
	function unfollowUser($user_id=null) {

		return $this->httpDelete($this->_baseUrl.'users/'.$user_id.'/follow');

	}

	// Returns an array of User objects the specified user is following.
	function getFollowing($user_id='me',$count=20,$before_id=null,$since_id=null) {
		return $this->httpGet($this->_baseUrl.'users/'.$user_id.'/following?count='
					.$count.'&before_id='.$before_id.'&since_id='.$since_id);
	}
	
	// Returns an array of User objects for users following the specified user.
	function getFollowers($user_id='me',$count=20,$before_id=null,$since_id=null) {
		return $this->httpGet($this->_baseUrl.'users/'.$user_id.'/followers?count='
					.$count.'&before_id='.$before_id.'&since_id='.$since_id);
	}
	
	// Return the 20 most recent Posts for a specific hashtag.
	function searchHashtags($hashtag=null,$count=20,$before_id=null,$since_id=null) {
		return $this->httpGet($this->_baseUrl.'posts/tag/'.$hashtag.'?count='
					.$count.'&before_id='.$before_id.'&since_id='.$since_id);
	}
	
	// Retrieve a personalized Stream for the current authorized User. This endpoint 
	// is similar to the 'Retrieve a User's personalized stream' endpoint.
	function getUserRealTimeStream($count=20,$before_id=null,$since_id=null) {
		return $this->httpGet($this->_baseUrl.'posts/stream?count='.$count
			.'&before_id='.$before_id.'&since_id='.$since_id);
	
	}
	
	// Retrieve a Stream of all public Posts on App.net.
	function getPublicPosts($count=20,$before_id=null,$since_id=null) {
		return $this->httpGet($this->_baseUrl.'posts/stream/global?count='
					.$count.'&before_id='.$before_id.'&since_id='.$since_id);
	}

	// Retrieve the current status for a specific Stream
	function getStreamStatus($stream_id=null) {

		return $this->httpGet($this->_baseUrl.'streams/'.$stream_id);

	}

	// Change the Posts returned in the specified Stream.
	function controlStream($stream_id=null, $data=array()) {

		return $this->httpPost($this->_baseUrl.'streams/'.$stream_id, $data);

	}

	// List all the Subscriptions this app is currently subscribed to. 
	// This resource must be accessed with an App access token.
	function listSubscriptions() {

		return $this->httpGet($this->_baseUrl.'subscriptions');

	}

	// Create a new subscription. Returns either 201 CREATED or an error 
	// status code. Please read the general subscription information to 
	// understand the entire subscription process. This resource must be 
	// accessed with an App access token.
	function createSubscription($object='user', $aspect=null, 
                                    $callback_url=null, $verify_token=null) {

		$params = array('object'=>$object, 'aspect'=>$aspect, 
                           'callback_url'=>$callback_url, 'verify_token'=>$verify_token);

		return $this->httpPost($this->_baseUrl.'subscriptions', $params);

	}

	// Delete a single subscription. Returns the deleted subscription. 
	// This resource must be accessed with an App access token.
	function deleteSubscription($subscription_id=null) {

		return $this->httpDelete($this->_baseUrl.'subscriptions/'.$subscription_id);

	}

	// Delete all subscriptions for the authorized App. Returns a list 
	// of the deleted subscriptions. This resource must be accessed with 
	// an App access token.
	function deleteAllSubscriptions() {

		return $this->httpDelete($this->_baseUrl.'subscriptions');

	}

	// workaround function to return userID by username
	function getIdByUsername($username=null) {
		$ch = curl_init('https://alpha.app.net/'.$username); 
		curl_setopt($ch, CURLOPT_POST, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch,CURLOPT_USERAGENT,
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:7.0.1) Gecko/20100101 Firefox/7.0.1');
		$response = curl_exec($ch); 
		curl_close($ch);
		$temp = explode('title="User Id ',$response);
		$temp2 = explode('"',$temp[1]);
		$user_id = $temp2[0];
		return $user_id;
	}

}
?>
