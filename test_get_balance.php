<?php
session_start();
require('test_entrecard.php');

if (!$_GET['oauth_token']) {
    // If there's no token, we didn't get the accept
    echo "Sorry, you declined us, so we can't proceed.";
    exit(0);
}

$oa = new OAuthEntrecard(SITE_USER_ID, SITE_SECRET);
if (!array_key_exists('access_token', $_SESSION)) {
    $my_url_path = dirname("http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    
    
    list($user_id, $token, $token_secret) = $oa->getAccessToken($_SESSION['request_token'], $_SESSION['request_token_secret'], $_GET['oauth_verifier']);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['access_token'] = $token;
    $_SESSION['access_token_secret'] = $token_secret;
}

$eca = $oa->getAuthority($_SESSION['user_id'], $_SESSION['access_token'], $_SESSION['access_token_secret']);

$user_id = $_SESSION['user_id'];

?>

User id: <?=$user_id?>

access token: <?=$token ?>

<img src="<?=$eca->getImageURL()?>" />

Your email address is <?=$eca->getUserEmail()?> and your balance is <?=$eca->getBalance()?>ec


Transferring 10 ec from user to our account (SITE_USER_ID)
<?

$prepare_id = $eca->prepareTransferCredits(10);
$new_balance = $eca->commitTransferCredits($prepare_id, SITE_USER_ID, "test transfer");

?>

New balance should be <?=$new_balance?>.

Trying to transfer credits back from consumer account

<?

$coa = new OAuthEntrecard(SITE_USER_ID, SITE_SECRET);

list($ctoken, $ctoken_secret) = $coa->getToken(array(EC_PERM_AUTHENTICATE, EC_PERM_BALANCE, EC_PERM_WITHDRAW), '');
$cverifier = $coa->consumerAuthenticate($ctoken);
list($cuser_id, $ctoken, $ctoken_secret) = $oa->getAccessToken($ctoken, $ctoken_secret, $cverifier);
$ceca = $coa->getAuthority($cuser_id, $ctoken, $ctoken_secret);

echo "uid: $user_id";
echo "cuid: $cuser_id";

$prepare_id = $ceca->prepareTransferCredits(10);
$new_balance = $ceca->commitTransferCredits($prepare_id, $user_id, "test transfer back");

?>
