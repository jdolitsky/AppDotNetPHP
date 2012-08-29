<?php
/**
 * AppDotNet.php
 * App.net PHP library
 * https://github.com/jdolitsky/AppDotNetPHP
 *
 * This class handles a lower level type of access to App.net. It's ideal
 * for command line scripts and other places where you want full control
 * over what's happening, and you're at least a little familiar with oAuth.
 * 
 * Alternatively you can use the EZAppDotNet class which automatically takes 
 * care of a lot of the details like logging in, keeping track of tokens,
 * etc. EZAppDotNet assumes you're accessing App.net via a browser, whereas
 * this class tries to make no assumptions at all.
 */

class AppDotNetException extends Exception {}

class AppDotNet {

	private $_baseUrl = 'https://alpha-api.app.net/stream/0/';
	private $_authUrl = 'https://alpha.app.net/oauth/';

	private $_authPostParams=array();

	// stores the access token after login
	private $_accessToken = null;

	// The total number of requests you're allowed within the alloted time period
	private $_rateLimit = null;

	// The number of requests you have remaining within the alloted time period
	private $_rateLimitRemaining = null;

	// The number of seconds remaining in the alloted time period
	private $_rateLimitReset = null;

	// constructor
	public function __construct($clientId,$clientSecret) {
		$this->_clientId = $clientId;
		$this->_clientSecret = $clientSecret;
	}

	/**
	 * Construct the proper Auth URL for the user to visit and either grant
	 * or not access to your app. Usually you would place this as a link for
	 * the user to client, or a redirect to send them to the auth URL.
	 * @param string $callbackUri Where you want the user to be directed
	 * after authenticating with App.net. This must be one of the URIs
	 * allowed by your App.net application settings.
	 * @param array $scope An array of scopes (permissions) you wish to obtain
	 * from the user. Currently options are stream, email, write_post, follow,
	 * messages, and export. If you don't specify anything, you'll only receive
	 * access to the user's basic profile (the default).
	 */
	public function getAuthUrl($callbackUri,$scope=null) {

		// construct an authorization url based on our client id and other data
		$data = array(
			'client_id'=>$this->_clientId,
			'response_type'=>'code',
			'redirect_uri'=>$callbackUri,
		);

		if ($scope) {
			$data['scope'] = implode('+',$scope);
		}

		// return the constructed url
		return $this->_authUrl.'authenticate?'.http_build_query($data);
	}

	/**
	 * Call this after they return from the auth page, or anytime you need the 
	 * token. For example, you could store it in a database and use 
	 * setAccessToken() later on to return on behalf of the user.
	 */
	public function getAccessToken() {
		// if there's no access token set, and they're returning from 
		// the auth page with a code, use the code to get a token
		if (!$this->_accessToken && isset($_GET['code']) && $_GET['code']) {

			// construct the necessary elements to get a token
			$data = array(
				'client_id'=>$this->_clientId,
				'client_secret'=>$this->_clientSecret,
				'grant_type'=>'authorization_code',
				'redirect_uri'=>$this->_redirectUri,
				'code'=>$_GET['code']
			);

			// try and fetch the token with the above data
			$res = $this->httpPost($this->_authUrl.'access_token', $data);

			// store it for later
			$this->_accessToken = $res['access_token'];
		}

		// return what we have (this may be a token, or it may be nothing)
		return $this->_accessToken;
	}

	// set the access token (eg: after retrieving it from offline storage)
	public function setAccessToken($token) {
		$this->_accessToken = $token;
	}

	// The total number of requests you're allowed within the alloted time period
	public function getRateLimit() {
		return $this->_rateLimit;
	}

	// The number of requests you have remaining within the alloted time period
	public function getRateLimitRemaining() {
		return $_rateLimitRemaining;
	}

	// The number of seconds remaining in the alloted time period
	// When this time is up you'll have getRateLimit() available again
	public function getRateLimitReset() {
		return $_rateLimitReset;
	}

	protected function parseHeaders($response) {
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
	protected function httpPost($req, $params=array()) {
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
	protected function httpGet($req) {
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
	protected function httpDelete($req, $params=array()) {
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
	public function getAllFilters() {
		
		return $this->httpGet($this->_baseUrl.'filters');

	}

	// Create a Filter for the current user.
	public function createFilter($name='New filter', $user_ids=array(), $hashtags=array(), 
                                     $link_domains=array(), $mention_user_ids= array()) {
		$params = array(
			'name'=>$name, 
			'user_ids'=>$user_ids, 
			'hashtags'=>$hashtags,
			'link_domains'=>$link_domains, 
			'mention_user_ids'=>$mention_user_ids
		);
		return $this->httpPost($this->_baseUrl.'filters',$params);
	}

	// Returns a specific Filter object.
	public function getFilter($filter_id=null) {
		
		return $this->httpGet($this->_baseUrl.'filters/'.$filter_id);

	}

	// Delete a Filter. The Filter must belong to the current User. 
	// It returns the deleted Filter on success.
	public function deleteFilter($filter_id=null) {
		
		return $this->httpDelete($this->_baseUrl.'filters');

	}

	// Create a new Post object. Mentions and hashtags will be parsed out of the 
	// post text, as will bare URLs. To create a link in a post without using a 
	// bare URL, include the anchor text in the post's text and include a link 
	// entity in the post creation call.
	public function createPost($text=null, $reply_to=null, $annotations=null, $links=null) {
		$params = array(
			'text'=>$text,
			'reply_to'=>$reply_to, 
			'annotations'=>$annotations, 
			'links'=>$links
		);
		return $this->httpPost($this->_baseUrl.'posts',$params);
	}

	// Returns a specific Post.
	public function getPost($post_id=null) {

		return $this->httpGet($this->_baseUrl.'posts/'.$post_id);

	}

	// Delete a Post. The current user must be the same user who created the Post. 
	// It returns the deleted Post on success.
	public function deletePost($post_id=null) {

		return $this->httpDelete($this->_baseUrl.'posts/'.$post_id);

	}

	// Retrieve the Posts that are 'in reply to' a specific Post.
	public function getPostReplies($post_id=null,$count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'posts/'.$post_id.'/replies?'.http_build_query($params));
	}
	
	// Get the most recent Posts created by a specific User in reverse 
	// chronological order.
	public function getUserPosts($user_id='me',$count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'users/'.$user_id.'/posts?'.http_build_query($params));
	}
	
	// Get the most recent Posts mentioning by a specific User in reverse 
	// chronological order.
	public function getUserMentions($user_id='me',$count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'users/'.$user_id.'/mentions?'.http_build_query($params));
	
	}

	// Return the 20 most recent Posts from the current User and 
	// the Users they follow.
	public function getUserStream($user_id='me',$count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'posts/stream?'.http_build_query($params));
	
	}

	// Returns a specific User object.
	public function getUser($user_id='me') {

		return $this->httpGet($this->_baseUrl.'users/'.$user_id);

	}

	// Returns the User object of the user being followed.
	public function followUser($user_id=null) {

		return $this->httpPost($this->_baseUrl.'users/'.$user_id.'/follow');

	}

	// Returns the User object of the user being unfollowed.
	public function unfollowUser($user_id=null) {

		return $this->httpDelete($this->_baseUrl.'users/'.$user_id.'/follow');

	}

	// Returns an array of User objects the specified user is following.
	public function getFollowing($user_id='me',$count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'users/'.$user_id.'/following?'.http_build_query($params));
	}
	
	// Returns an array of User objects for users following the specified user.
	public function getFollowers($user_id='me',$count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'users/'.$user_id.'/followers?'.http_build_query($params));
	}
	
	// Return the 20 most recent Posts for a specific hashtag.
	public function searchHashtags($hashtag=null,$count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'posts/tag/'.$hashtag.'?'.http_build_query($params));
	}
	
	// Retrieve a personalized Stream for the current authorized User. This endpoint 
	// is similar to the 'Retrieve a User's personalized stream' endpoint.
	public function getUserRealTimeStream($count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'posts/stream?'.http_build_query($params));
	
	}
	
	// Retrieve a personalized Stream for the specified users. This endpoint is similar
	// to the 'Retrieve a User's personalized stream' endpoint.
	public function getUsersRealTimeStream($user_ids=null,$count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($user_ids) {
			$params['user_ids'] = json_encode($user_ids);
		}
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'streams/app?'.http_build_query($params));
	}
	
	// Retrieve a Stream of all public Posts on App.net.
	public function getPublicPosts($count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'posts/stream/global?'.http_build_query($params));
	}

	// Retrieve the current status for a specific Stream
	public function getStreamStatus($stream_id=null) {

		return $this->httpGet($this->_baseUrl.'streams/'.$stream_id);

	}

	// Change the Posts returned in the specified Stream.
	public function controlStream($stream_id=null, $data=array()) {

		return $this->httpPost($this->_baseUrl.'streams/'.$stream_id, $data);

	}

	// List all the Subscriptions this app is currently subscribed to. 
	// This resource must be accessed with an App access token.
	public function listSubscriptions() {

		return $this->httpGet($this->_baseUrl.'subscriptions');

	}

	// Create a new subscription. Returns either 201 CREATED or an error 
	// status code. Please read the general subscription information to 
	// understand the entire subscription process. This resource must be 
	// accessed with an App access token.
	public function createSubscription($object='user', $aspect=null, 
                                    $callback_url=null, $verify_token=null) {

		$params = array(
			'object'=>$object, 
			'aspect'=>$aspect, 
			'callback_url'=>$callback_url, 
			'verify_token'=>$verify_token
		);

		return $this->httpPost($this->_baseUrl.'subscriptions', $params);

	}

	// Delete a single subscription. Returns the deleted subscription. 
	// This resource must be accessed with an App access token.
	public function deleteSubscription($subscription_id=null) {

		return $this->httpDelete($this->_baseUrl.'subscriptions/'.$subscription_id);

	}

	// Delete all subscriptions for the authorized App. Returns a list 
	// of the deleted subscriptions. This resource must be accessed with 
	// an App access token.
	public function deleteAllSubscriptions() {

		return $this->httpDelete($this->_baseUrl.'subscriptions');

	}

	// workaround function to return userID by username
	public function getIdByUsername($username=null) {
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
	
	// Mute user
	public function muteUser($user_id=null) {
	 	return $this->httpPost($this->_baseUrl.'users/'.$user_id.'/mute');
	}   
	
	// Unmute user
	public function unmuteUser($user_id=null) {
		return $this->httpDelete($this->_baseUrl.'users/'.$user_id.'/mute');
	}       
	
	// List the users muted by the current user
	public function getMuted($user_id='me',$count=null,$before_id=null,$since_id=null) {
		$params = array();
		if ($count) {
			$params['count'] = $count;
		}
		if ($before_id) {
			$params['before_id'] = $before_id;
		}
		if ($since_id) {
			$params['since_id'] = $since_id;
		}
		return $this->httpGet($this->_baseUrl.'users/'.$user_id.'/muted?'.http_build_query($params));
	}

}
