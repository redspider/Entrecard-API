<?php
session_start();
require('test_entrecard.php');

$my_url_path = dirname("http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);

$oa = new OAuthEntrecard(SITE_USER_ID, SITE_SECRET);
list($token, $token_secret) = $oa->getToken(array(EC_PERM_AUTHENTICATE, EC_PERM_BALANCE, EC_PERM_WITHDRAW), $my_url_path."/test_get_balance.php");

/* Need to store token and token secret somewhere. You have two options,
  either put the token and token secret into the session, in which case
  you can only act on the users behalf during a request in the current
  session (fine for basic services).
  
  Or you can store the token and secret in the database. THis allows
  you to act on the users behalf after the session expires or in
  a cron job.
*/

$_SESSION['request_token'] = $token;
$_SESSION['request_token_secret'] = $token_secret;
unset($_SESSION['access_token']);


$authentication_url = $oa->getAuthenticationURL($token, $token_secret);

// Redirect user to authentication URL
Header('Location: '.$authentication_url);

?>