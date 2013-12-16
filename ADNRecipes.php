<?php

require_once "AppDotNet.php";

class ADNRecipe {
    protected $_adn = null;

	public function __construct() {
        $this->_adn = new AppDotNet(null, null);
    }

    public function setAccessToken($access_token) {
        $this->_adn->setAccessToken($access_token);
    }
}

class ADNBroadcastMessageBuilder extends ADNRecipe {
    // stores the channel ID for this message
    private $_channel_id = null;

    // stores the headline
    private $_headline = null;

    // stores the body text
    private $_text = null;

    // should we parse markdown links?
    private $_parseMarkdownLinks = false;

    // should we parse URLs out of the text body?
    private $_parseLinks = false;

    // stores the read more link
    private $_readMoreLink = null;

    // stores the photo filename
    private $_photo = null;

    // stores the attachment filename
    private $_attachment = null;

    public function setChannelID($channel_id) {
        $this->_channel_id = $channel_id;

        return $this;
    }

    public function getChannelID() {
        return $this->_channel_id;
    }

    public function setHeadline($headline) {
        $this->_headline = $headline;

        return $this;
    }

    public function getHeadline() {
        return $this->_headline;
    }

    public function setText($text) {
        $this->_text = $text;

        return $this;
    }

    public function getText() {
        return $this->_text;
    }

    public function setParseMarkdownLinks($parseMarkdownLinks) {
        $this->_parseMarkdownLinks = $parseMarkdownLinks;

        return $this;
    }

    public function getParseMarkdownLinks() {
        return $this->_parseMarkdownLinks;
    }

    public function setParseLinks($parseLinks) {
        $this->_parseLinks = $parseLinks;

        return $this;
    }

    public function getParseLinks() {
        return $this->_parseLinks;
    }

    public function setReadMoreLink($readMoreLink) {
        $this->_readMoreLink = $readMoreLink;

        return $this;
    }

    public function getReadMoreLink() {
        return $this->_readMoreLink;
    }

    public function setPhoto($photo) {
        $this->_photo = $photo;

        return $this;
    }

    public function getPhoto() {
        return $this->_photo;
    }

    public function setAttachment($attachment) {
        $this->_attachment = $attachment;

        return $this;
    }

    public function getAttachment() {
        return $this->_attachment;
    }

    public function send() {
        $parseLinks = $this->_parseLinks || $this->_parseMarkdownLinks;
        $message = array(
            "annotations" => array(),
            "entities" => array(
                "parse_links" => $parseLinks,
                "parse_markdown_links" => $this->_parseMarkdownLinks,
            ),
        );

        if (isset($this->_photo)) {
            $photoFile = $this->_adn->createFile($this->_photo, array(
                type => "com.github.jdolitsky.appdotnetphp.photo",
            ));

            $message["annotations"][] = array(
                "type" => "net.app.core.oembed",
                "value" => array(
                    "+net.app.core.file" => array(
                        "file_id" => $photoFile["id"],
                        "file_token" => $photoFile["file_token"],
                        "format" => "oembed",
                    ),
                ),
            );
        }

        if (isset($this->_attachment)) {
            if (isset($this->_attachment)) {
                $attachmentFile = $this->_adn->createFile($this->_attachment, array(
                    type => "com.github.jdolitsky.appdotnetphp.attachment",
                ));

                $message["annotations"][] = array(
                    "type" => "net.app.core.oembed",
                    "value" => array(
                        "+net.app.core.file" => array(
                            "file_id" => $attachmentFile["id"],
                            "file_token" => $attachmentFile["file_token"],
                            "format" => "metadata",
                        ),
                    ),
                );
            }
        }

        if (isset($this->_text)) {
            $message["text"] = $this->_text;
        } else {
            $message["machine_only"] = true;
        }

        if (isset($this->_headline)) {
            $message["annotations"][] = array(
                "type" => "net.app.core.broadcast.message.metadata",
                "value" => array(
                    "subject" => $this->_headline,
                ),
            );
        }

        if (isset($this->_readMoreLink)) {
            $message["annotations"][] = array(
                "type" => "net.app.core.crosspost",
                "value" => array(
                    "canonical_url" => $this->_readMoreLink,
                ),
            );
        }

        return $this->_adn->createMessage($this->_channel_id, $message);
    }
}







?>
