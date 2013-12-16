<?php

require_once "../ADNRecipes.php";

$builder = new ADNBroadcastMessageBuilder();

$builder->setAccessToken("<your access token here>");

// supports chained builder syntax!
$builder->setChannelID(24204)
    ->setHeadline("Hello world!")
    ->setText("Sent via [AppDotNetPHP](https://github.com/jdolitsky/AppDotNetPHP)!")
    ->setParseMarkdownLinks(true)
    ->setReadMoreLink("http://www.google.com");

// If you have imagemagick installed, can support gifs (and other photos) too!
// $builder->setPhoto("giphy.gif");

$builder->send();
?>
