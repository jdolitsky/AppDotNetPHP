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
class AppDotNet {

	private $_baseUrl = 'https://alpha-api.app.net/stream/0/';
	private $_authUrl = 'https://alpha.app.net/oauth/';

	private $_authPostParams=array();

	// stores the access token after login
	private $_accessToken = null;

	// stores the user ID returned when fetching the auth token
	private $_user_id = null;

	// stores the username returned when fetching the auth token
	private $_username = null;

	// The total number of requests you're allowed within the alloted time period
	private $_rateLimit = null;

	// The number of requests you have remaining within the alloted time period
	private $_rateLimitRemaining = null;

	// The number of seconds remaining in the alloted time period
	private $_rateLimitReset = null;

	// debug info
	private $_last_request = null;
	private $_last_response = null;

	/**
	 * Constructs an AppDotNet PHP object with the specified client ID and 
	 * client secret.
	 * @param string $client_id The client ID you received from App.net when 
	 * creating your app.
	 * @param string $client_secret The client secret you received from 
	 * App.net when creating your app.
	 */
	public function __construct($client_id,$client_secret) {
		$this->_clientId = $client_id;
		$this->_clientSecret = $client_secret;
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
	public function getAuthUrl($callback_uri,$scope=null) {

		// construct an authorization url based on our client id and other data
		$data = array(
			'client_id'=>$this->_clientId,
			'response_type'=>'code',
			'redirect_uri'=>$callback_uri,
		);

		$url = $this->_authUrl.'authenticate?'.$this->buildQueryString($data);
		if ($scope) {
			$url .= '&scope='.implode('+',$scope);
		}

		// return the constructed url
		return $url;
	}

	/**
	 * Call this after they return from the auth page, or anytime you need the 
	 * token. For example, you could store it in a database and use 
	 * setAccessToken() later on to return on behalf of the user.
	 */
	public function getAccessToken($callback_uri) {
		// if there's no access token set, and they're returning from 
		// the auth page with a code, use the code to get a token
		if (!$this->_accessToken && isset($_GET['code']) && $_GET['code']) {

			// construct the necessary elements to get a token
			$data = array(
				'client_id'=>$this->_clientId,
				'client_secret'=>$this->_clientSecret,
				'grant_type'=>'authorization_code',
				'redirect_uri'=>$callback_uri,
				'code'=>$_GET['code']
			);

			// try and fetch the token with the above data
			$res = $this->httpReq('post',$this->_authUrl.'access_token', $data);

			// store it for later
			$this->_accessToken = $res['access_token'];
			$this->_username = $res['username'];
			$this->_user_id = $res['user_id'];
		}

		// return what we have (this may be a token, or it may be nothing)
		return $this->_accessToken;
	}

	/**
	 * Set the access token (eg: after retrieving it from offline storage)
	 * @param string $token A valid access token you're previously received 
	 * from calling getAccessToken().
	 */
	public function setAccessToken($token) {
		$this->_accessToken = $token;
	}

	/**
	 * Returns the total number of requests you're allowed within the 
	 * alloted time period.
	 * @see getRateLimitReset()
	 */
	public function getRateLimit() {
		return $this->_rateLimit;
	}

	/**
	 * The number of requests you have remaining within the alloted time period
	 * @see getRateLimitReset()
	 */
	public function getRateLimitRemaining() {
		return $_rateLimitRemaining;
	}

	/**
	 * The number of seconds remaining in the alloted time period.
	 * When this time is up you'll have getRateLimit() available again.
	 */
	public function getRateLimitReset() {
		return $_rateLimitReset;
	}

	/**
	 * Internal function, parses out important information App.net adds
	 * to the headers.
	 */
	protected function parseHeaders($response) {
		// take out the headers
		// set internal variables
		// return the body/content
		$this->rateLimit = null;
		$this->rateLimitRemaining = null;
		$this->rateLimitReset = null;

		$response = explode("\r\n\r\n",$response,2);
		$headers = $response[0];
		if (isset($response[1])) {
			$content = $response[1];
		}
		else {
			$content = null;
		}

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

	/** 
	 * Internal function. Used to turn things like TRUE into 1, and then
	 * calls http_build_query.
	 */
	protected function buildQueryString($array) {
		foreach ($array as $k=>&$v) {
			if ($v===true) {
				$v = '1';
			}
			elseif ($v===false) {
				$v = '0';
			}
			unset($v);
		}
		return http_build_query($array);
	}

	
	/** 
	 * Internal function to handle all 
	 * HTTP requests (POST,GET,DELETE)
	 */
	protected function httpReq($act, $req, $params=array(),$contentType='application/x-www-form-urlencoded') {
		$ch = curl_init($req); 
		$headers = array();
		if($act == 'post' || $act == 'delete') {
			curl_setopt($ch, CURLOPT_POST, true);
			// if they passed an array, build a list of parameters from it
			if (is_array($params)) {
				$params = $this->buildQueryString($params);
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
			$headers[] = "Content-Type: ".$contentType;
		}
		if($act == 'delete') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		}
		if ($this->_accessToken) {
			$headers[] = 'Authorization: Bearer '.$this->_accessToken;
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		$this->_last_response = curl_exec($ch); 
		$this->_last_request = curl_getinfo($ch,CURLINFO_HEADER_OUT);
		curl_close($ch);
		$response = $this->parseHeaders($this->_last_response);
		$response = json_decode($response,true);
		if (isset($response['error'])) {
			if (is_array($response['error'])) {
				throw new AppDotNetException($response['error']['message'],
								$response['error']['code']);
			}
			else {
				throw new AppDotNetException($response['error']);
			}
		} else {
			return $response;
		}
	}

	/**
	 * Return the Filters for the current user.
	 */
	public function getAllFilters() {
		return $this->httpReq('get',$this->_baseUrl.'filters');
	}

	/**
	 * Create a Filter for the current user.
	 * @param string $name The name of the new filter
	 * @param array $filters An associative array of filters to be applied.
	 * This may change as the API evolves, as of this writing possible
	 * values are: user_ids, hashtags, link_domains, and mention_user_ids.
	 * You will need to provide at least one filter name=>value pair.
	 */
	public function createFilter($name='New filter', $filters=array()) {
		$filters['name'] = $name;
		return $this->httpReq('post',$this->_baseUrl.'filters',$filters);
	}

	/**
	 * Returns a specific Filter object.
	 * @param integer $filter_id The ID of the filter you wish to retrieve.
	 */
	public function getFilter($filter_id=null) {
		return $this->httpReq('get',$this->_baseUrl.'filters/'.urlencode($filter_id));
	}

	/**
	 * Delete a Filter. The Filter must belong to the current User. 
	 * @return object Returns the deleted Filter on success.
	 */
	public function deleteFilter($filter_id=null) {
		return $this->httpReq('delete',$this->_baseUrl.'filters/'.urlencode($filter_id));
	}

	/**
	 * Create a new Post object. Mentions and hashtags will be parsed out of the 
	 * post text, as will bare URLs. To create a link in a post without using a 
	 * bare URL, include the anchor text in the post's text and include a link 
	 * entity in the post creation call.
	 * @param string $text The text of the post
	 * @param array $data An associative array of optional post data. This
	 * will likely change as the API evolves, as of this writing allowed keys are:
	 * reply_to, and annotations. "annotations" may be a complex object represented
	 * by an associative array.
	 * @return array An associative array representing the post.
	 */
	public function createPost($text=null, $data = array()) {
		$data['text'] = $text;
		$json = json_encode($data);
		return $this->httpReq('post',$this->_baseUrl.'posts',$json,'application/json');
	}

	/**
	 * Returns a specific Post.
	 * @param integer $post_id The ID of the post to retrieve
	 * @param array $params An associative array of optional general parameters. 
	 * This will likely change as the API evolves, as of this writing allowed keys 
	 * are: include_annotations.
	 * @return array An associative array representing the post
	 */
	public function getPost($post_id=null,$params = array()) {
		return $this->httpReq('get',$this->_baseUrl.'posts/'.urlencode($post_id)
						.'?'.$this->buildQueryString($params));
	}

	/**
	 * Delete a Post. The current user must be the same user who created the Post. 
	 * It returns the deleted Post on success.
	 * @param integer $post_id The ID of the post to delete
	 * @param array An associative array representing the post that was deleted
	 */
	public function deletePost($post_id=null) {
		return $this->httpReq('delete',$this->_baseUrl.'posts/'.urlencode($post_id));
	}

	/**
	 * Retrieve the Posts that are 'in reply to' a specific Post.
	 * @param integer $post_id The ID of the post you want to retrieve replies for.
	 * @param array $params An associative array of optional general parameters. 
	 * This will likely change as the API evolves, as of this writing allowed keys 
	 * are:	count, before_id, since_id, include_muted, include_deleted, 
	 * include_directed_posts, and include_annotations.
	 * @return An array of associative arrays, each representing a single post.
	 */
	public function getPostReplies($post_id=null,$params = array()) {
		return $this->httpReq('get',$this->_baseUrl.'posts/'.urlencode($post_id)
				.'/replies?'.$this->buildQueryString($params));
	}

	/**
	 * Get the most recent Posts created by a specific User in reverse 
	 * chronological order (most recent first).
	 * @param mixed $user_id Either the ID of the user you wish to retrieve posts by,
	 * or the string "me", which will retrieve posts for the user you're authenticated
	 * as.
	 * @param array $params An associative array of optional general parameters. 
	 * This will likely change as the API evolves, as of this writing allowed keys 
	 * are:	count, before_id, since_id, include_muted, include_deleted, 
	 * include_directed_posts, and include_annotations.
	 * @return An array of associative arrays, each representing a single post.
	 */
	public function getUserPosts($user_id='me', $params = array()) {
		return $this->httpReq('get',$this->_baseUrl.'users/'.urlencode($user_id)
					.'/posts?'.$this->buildQueryString($params));
	}
	
	/**
	 * Get the most recent Posts mentioning by a specific User in reverse 
	 * chronological order (newest first).
	 * @param mixed $user_id Either the ID of the user who is being mentioned, or 
	 * the string "me", which will retrieve posts for the user you're authenticated
	 * as.
	 * @param array $params An associative array of optional general parameters. 
	 * This will likely change as the API evolves, as of this writing allowed keys 
	 * are:	count, before_id, since_id, include_muted, include_deleted, 
	 * include_directed_posts, and include_annotations.
	 * @return An array of associative arrays, each representing a single post.
	 */
	public function getUserMentions($user_id='me',$params = array()) {
		return $this->httpReq('get',$this->_baseUrl.'users/'
			.urlencode($user_id).'/mentions?'.$this->buildQueryString($params));
	}

	/**
	 * Return the 20 most recent posts from the current User and 
	 * the Users they follow.
	 * @param array $params An associative array of optional general parameters. 
	 * This will likely change as the API evolves, as of this writing allowed keys 
	 * are:	count, before_id, since_id, include_muted, include_deleted, 
	 * include_directed_posts, and include_annotations.
	 * @return An array of associative arrays, each representing a single post.
	 */
	public function getUserStream($params = array()) {
		return $this->httpReq('get',$this->_baseUrl.'posts/stream?'.$this->buildQueryString($params));
	}

	/**
	 * Returns a specific user object.
	 * @param mixed $user_id The ID of the user you want to retrieve, or the string
	 * "me" to retrieve data for the users you're currently authenticated as.
	 * @return array An associative array representing the user data.
	 */
	public function getUser($user_id='me') {
		return $this->httpReq('get',$this->_baseUrl.'users/'.urlencode($user_id));
	}

	/**
	 * Add the specified user ID to the list of users followed.
	 * Returns the User object of the user being followed.
	 * @param integer $user_id The user ID of the user to follow.
	 * @return array An associative array representing the user you just followed.
	 */
	public function followUser($user_id=null) {
		return $this->httpReq('post',$this->_baseUrl.'users/'.urlencode($user_id).'/follow');
	}

	/**
	 * Removes the specified user ID to the list of users followed.
	 * Returns the User object of the user being unfollowed.
	 * @param integer $user_id The user ID of the user to unfollow.
	 * @return array An associative array representing the user you just unfollowed.
	 */
	public function unfollowUser($user_id=null) {
		return $this->httpReq('delete',$this->_baseUrl.'users/'.urlencode($user_id).'/follow');
	}

	/**
	 * Returns an array of User objects the specified user is following.
	 * @param mixed $user_id Either the ID of the user being followed, or 
	 * the string "me", which will retrieve posts for the user you're authenticated
	 * as.
	 * @return array An array of associative arrays, each representing a single 
	 * user following $user_id
	 */
	public function getFollowing($user_id='me') {
		return $this->httpReq('get',$this->_baseUrl.'users/'.$user_id.'/following');
	}
	
	/**
	 * Returns an array of User objects for users following the specified user.
	 * @param mixed $user_id Either the ID of the user being followed, or 
	 * the string "me", which will retrieve posts for the user you're authenticated
	 * as.
	 * @return array An array of associative arrays, each representing a single 
	 * user following $user_id
	 */
	public function getFollowers($user_id='me') {
		return $this->httpReq('get',$this->_baseUrl.'users/'.$user_id.'/followers');
	}

	/**
	 * Return Posts matching a specific #hashtag.
	 * @param string $hashtag The hashtag you're looking for.
	 * @param array $params An associative array of optional general parameters. 
	 * This will likely change as the API evolves, as of this writing allowed keys 
	 * are:	count, before_id, since_id, include_muted, include_deleted, 
	 * include_directed_posts, and include_annotations.
	 * @return An array of associative arrays, each representing a single post.
	 */
	public function searchHashtags($hashtag=null, $params = array()) {
		return $this->httpReq('get',$this->_baseUrl.'posts/tag/'
				.urlencode($hashtag).'?'.$this->buildQueryString($params));
	}

	/**
	 * Retrieve a list of all public Posts on App.net, often referred to as the
	 * global stream.
	 * @param array $params An associative array of optional general parameters. 
	 * This will likely change as the API evolves, as of this writing allowed keys 
	 * are:	count, before_id, since_id, include_muted, include_deleted, 
	 * include_directed_posts, and include_annotations.
	 * @return An array of associative arrays, each representing a single post.
	 */
	public function getPublicPosts($params = array()) {
		return $this->httpReq('get',$this->_baseUrl.'posts/stream/global?'.$this->buildQueryString($params));
	}

	/**
	 * Retrieve a user's user ID by specifying their username.
	 * Not currently supported by the API, so we scrape the alpha.app.net site for the info.
	 * @param string $username The username of the user you want the ID of, without
	 * an @ symbol at the beginning.
	 * @return integer The user's user ID
	 */
	public function getIdByUsername($username=null) {
		$ch = curl_init('https://alpha.app.net/'.urlencode(strtolower($username))); 
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
	
	/**
	 * Mute a user
	 * @param integer $user_id The user ID to mute
	 */
	public function muteUser($user_id=null) {
	 	return $this->httpReq('post',$this->_baseUrl.'users/'.urlencode($user_id).'/mute');
	}   
	
	/**
	 * Unmute a user
	 * @param integer $user_id The user ID to unmute
	 */
	public function unmuteUser($user_id=null) {
		return $this->httpReq('delete',$this->_baseUrl.'users/'.urlencode($user_id).'/mute');
	}       

	/**
	 * List the users muted by the current user
	 * @return array An array of associative arrays, each representing one muted user.
	 */
	public function getMuted() {
		return $this->httpReq('get',$this->_baseUrl.'users/me/muted');
	}

	/**
	* Star a post
	* @param integer $post_id The post ID to star
	*/
	public function starPost($post_id=null) {
		return $this->httpReq('post',$this->_baseUrl.'posts/'.urlencode($post_id).'/star');
	}

	/**
	* Unstar a post
	* @param integer $post_id The post ID to unstar
	*/
	public function unstarPost($post_id=null) {
		return $this->httpReq('delete',$this->_baseUrl.'posts/'.urlencode($post_id).'/star');
	}

	/**
	* List the posts starred by the current user
	* @param array $params An associative array of optional general parameters. 
	* This will likely change as the API evolves, as of this writing allowed keys 
	* are:	count, before_id, since_id, include_muted, include_deleted, 
	* include_directed_posts, and include_annotations.
	* See https://github.com/appdotnet/api-spec/blob/master/resources/posts.md#general-parameters
	* @return array An array of associative arrays, each representing a single 
	* user who has starred a post
	*/
	public function getStarred($user_id='me', $params = array()) {
		return $this->httpReq('get',$this->_baseUrl.'users/'.urlencode($user_id).'/stars'
					.'?'.$this->buildQueryString($params));
	}

	/**
	* List the users who have starred a post
	* @param integer $post_id the post ID to get stars from
	* @return array An array of associative arrays, each representing one user.
	*/
	public function getStars($post_id=null) {
		return $this->httpReq('get',$this->_baseUrl.'posts/'.$post_id.'/stars');
	}

	/**
	 * Returns an array of User objects of users who reposted the specified post.
	 * @param integer $post_id the post ID to 
	 * @return array An array of associative arrays, each representing a single 
	 * user who reposted $post_id
	 */
	public function getReposters($post_id){
		return $this->httpReq('get',$this->_baseUrl.'posts/'.$post_id.'/reposters'); 
	}

	/**
	 * Repost an existing Post object.
	 * @param integer $post_id The id of the post
	 * @return not a clue
	 */
	public function repost($post_id){
		return $this->httpReq('post',$this->_baseUrl.'posts/'.$post_id.'/repost');
	}

	/**
	 * Delete a post that the user has reposted.
	 * @param integer $post_id The id of the post
	 * @return not a clue
	 */
	public function deleteRepost($post_id){
		return $this->httpReq('delete',$this->_baseUrl.'posts/'.$post_id.'/repost');
	}

	/**
	* List the users who match a specific search term
	* @param string $search The search query. Supports @username or #tag searches as
	* well as normal search terms. Searches username, display name, bio information.
	* Does not search posts.
	* @return array An array of associative arrays, each representing one user.
	*/
	public function searchUsers($search="") {
		return $this->httpReq('get',$this->_baseUrl.'users/search?q='.urlencode($search));
	}

	public function getLastRequest() {
		return $this->_last_request;
	}
	public function getLastResponse() {
		return $this->_last_response;
	}

}

class AppDotNetException extends Exception {}
