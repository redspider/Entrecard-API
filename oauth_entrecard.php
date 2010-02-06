<?php

define("ENTRECARD_API_URL","http://entrecard.com/oapi");
#define("ENTRECARD_API_URL","http://localhost:8080/oapi");

class OAuthEntrecard {
    protected $site_id;
    protected $secret;
    protected $return_url;
    
    function __construct($site_user_id, $site_secret) {
        $this->site_id = $site_user_id;
        $this->secret = $site_secret;
    }
    
    function getToken($permissions, $callback_url) {
        /* Get a URL to send user to to authenticate for given permissions */
        $oauth = new OAuth($this->site_id, $this->secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        
        $token_info = $oauth->getRequestToken(ENTRECARD_API_URL."/request_token?permissions=".join(',',$permissions), urlencode($callback_url));
        return array($token_info['oauth_token'], $token_info['oauth_token_secret']);
    }
    
    function getAuthenticationURL($token, $token_secret) {
        return ENTRECARD_API_URL."/authorize?oauth_token=".$token;
    }
    
    function consumerAuthenticate($token) {
        /* Fast path authentication to authenticate token for yourself */
        $json = json_decode(file_get_contents(ENTRECARD_API_URL."/consumer_authorize?oauth_token=".$token), True);
        return $json['verifier'];
    }
    
    function getAccessToken($token, $token_secret, $verifier) {
        $oauth = new OAuth($this->site_id, $this->secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $oauth->setToken($token, $token_secret);
        $token_info = $oauth->getAccessToken(ENTRECARD_API_URL."/access_token", null, $verifier);
        return array($token_info['user_id'], $token_info['oauth_token'], $token_info['oauth_token_secret']);
    }
    
    function getAuthority($user_id, $token, $token_secret) {
        return new OAuthEntrecardAuthority($this->site_id, $this->secret, $user_id, $token, $token_secret);
    }
}

class OAuthEntrecardAuthority {
    protected $token;
    protected $token_secret;
    protected $user_id;
    protected $email;
    protected $card_id;
    protected $site_url;
    protected $site_id;
    protected $secret;
    
    function __construct($site_id, $secret, $user_id, $token, $token_secret) {
        $this->site_id = $site_id;
        $this->secret = $secret;
        $this->user_id = $user_id;
        $this->token = $token;
        $this->token_secret = $token_secret;
        $this->email = null;
    }
    
    function oauth() {
        $oauth = new OAuth($this->site_id, $this->secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $oauth->setToken($this->token, $this->token_secret);
        return $oauth;
    }
    
    function fetchBasic() {
        if ($this->email != null) {
            return;
        }
        $oauth = $this->oauth();
        $oauth->fetch(ENTRECARD_API_URL."/basic?user_id=".$this->user_id);
        $json = json_decode($oauth->getLastResponse(),True);
        $this->email = $json['email'];
        $this->card_id = $json['card_id'];
        $this->site_url = $json['site_url'];
    }
    
    function getAccessToken() {
        /* Get access token */
        return $this->access_token;
    }
    
    function getUserID() {
        /* Return the entrecard ID# for this user */
        return $this->user_id;
    }
    
    function getUserEmail() {
        /* Return the email address for this user */
        $this->fetchBasic();
        return $this->email;
    }
    
    function getBalance() {
        /* Return the current EC balance for this user */
        $oauth = $this->oauth();
        $oauth->fetch(ENTRECARD_API_URL."/balance?user_id=".$this->user_id);
        $json = json_decode($oauth->getLastResponse(), True);
        return $json['balance'];
    }
    
    function prepareTransferCredits($total) {
        /* Prepare to transfer credits, returns a prepare ID needed for commit */
        /* Returns the prepare id */
        $oauth = $this->oauth();
        $oauth->fetch(ENTRECARD_API_URL."/prepare?user_id=".$this->user_id."&total=".$total);
        $json = json_decode($oauth->getLastResponse(), True);
        return $json['prepare_id'];
    }
    
    function commitTransferCredits($prepare_id, $to_user_id, $note) {
        /* Commit a transfer of credits to $to_user_id using previously prepared $prepare_id */
        /* Returns the new balance of the sending user */
        $oauth = $this->oauth();
        $oauth->fetch(ENTRECARD_API_URL."/commit?user_id=".$this->user_id."&prepare_id=".$prepare_id."&credit_id=".$to_user_id."&note=".urlencode($note));
        $json = json_decode($oauth->getLastResponse(), True);
        return $json['balance'];
    }
    
    function getImageURL() {
        /* Get current Entrecard image URL for this user */
        $this->fetchBasic();
        return "http://entrecard.s3.amazonaws.com/eimage/".$this->card_id.".jpg";
    }
    
    function getProfileURL() {
        /* Get current Entrecard profile URL for this user */
        $this->fetchBasic();
        return "http://entrecard.com/details/".$this->card_id;
    }
    
    function getSiteURL() {
        $this->fetchBasic();
        return $this->site_url;
    }
}

?>