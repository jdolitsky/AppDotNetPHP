<?php

require_once 'AppDotNet.php';

$app = new AppDotNet();

// check that the user is signed in
if ($app->getSession()) {

	// get the current user as JSON
	$data = $app->getUser();

	// accessing the user's cover image
	echo '<body style="background:url('.$data['cover_image']['url'].')">';
	echo '<div style="background:#fff;opacity:0.8;padding:20px;margin:10px;border-radius:15px;">';
	echo '<h1>Welcome to <a target="_blank" href="https://github.com/jdolitsky/AppDotNetPHP">';
	echo 'AppDotNetPHP</a></h1>';

	// accessing the user's name
	echo '<h3>'.$data['name'].'</h3>';
	
	// accessing the user's avatar image
	echo '<img style="border:2px solid #000;" src="'.$data['avatar_image']['url'].'" /><br>';

	// try posting to ADN
	if (isset($_POST['run_tests'])) {
		print "<hr />";
		print "<h3>Testing ADN functionality</h3>";
		print '<pre>';
		print "<b>Attempting to post a test message to app.net...</b>\n";
		print "<blockquote>";
		$sampleText = "Testing posting to app.net using AppDotNetPHP - ".uniqid(mt_rand(0,100000));
		$create = $app->createPost($sampleText);
		// we should now have a post ID and the text should be the same as above
		if (!$create || !$create['id'] || $create['text']!=$sampleText) {
			print "Error posting sample text to ADN\n";
			var_dump($create);
			exit;
		}
		print "Successfully posted to ADN, post ID is ".$create['id']."\n";
		print "</blockquote>";

		// try fetching the post
		print "<b>Attempting to fetch sample post from app.net...</b>\n";
		print "<blockquote>";
		$get = $app->getPost($create['id']);
		if (!$get || !$get['id'] || $get['id']!=$create['id'] || $get['text']!=$sampleText) {
			print "Error fetching sample post from ADN:\n";
			var_dump($get);
			exit;
		}
		print "Successfully retrieved the sample post from ADN, post ID is ".$get['id']."\n";
		print "</blockquote>";

		// try deleting the post
		print "<b>Attempting to delete the sample post from app.net...</b>\n";
		print "<blockquote>";
		$delete = $app->deletePost($create['id']);
		if (!$delete || !$delete['id'] || $delete['id']!=$create['id']) {
			print "Error deleting sample post from ADN:\n";
			var_dump($delete);
			exit;
		}
		print "Successfully deleted the sample post from ADN, post ID was ".$delete['id']."\n";
		print "</blockquote>";

		// more tests can/should be included here

		// done tests!
		print "<b>All test completed successfully!</b>\n";
		print "</pre>";
	}

	else {
		print "<hr />";
		print "<h3>Complete user data</h3>";
		echo '<pre style="font-weight:bold;font-size:16px">';
		print_r($data);
		echo '</pre>';
	}

	print "<hr />";
	print '<form method="POST" action="index.php"><input type="submit" name="run_tests" value="Run POST/GET/DELETE tests" /><br />This will post a test message to your stream under your name, fetch it, then delete it.</form>';

	print "<hr />";
	echo '<h2><a href="signout.php">Sign out</a></h2>';

	echo '</div></body>';

// otherwise prompt to sign in
} else {

	$url = $app->getAuthUrl();
	echo '<a href="'.$url.'"><h2>Sign in using App.net</h2></a>';

}

?>



