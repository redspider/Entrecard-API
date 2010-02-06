## Entrecard API

This project provides a PHP wrapper to the Entrecard OAuth API. The API allows you (the Consumer) to perform a number of operations, including credit transfers, on behalf of an Entrecard user in a secure fashion.

It is recommended that developers intending to use the API have a passing familiarity with OAuth ( http://oauth.net/ ), although it is not necessary in order to use the wrapper.

### Definitions

The API generally follows the OAuth terminology, the following terms will be useful in understanding this document:

*   Service Provider

    In this case, the Service Provider is Entrecard.
    
*   Consumer

    The Consumer is the web service accessing the Service Provider on behalf of the User. In this case, the Consumer is probably you.
    
*   User

    The User is the account on whose behalf the Consumer is operating.

### Installation

The only file that is actually necessary is the oauth_entrecard.php file. This file needs the PHP oauth extension installed, which you can find at http://pecl.php.net/package/oauth

Quick note: If you're attempting to install the oauth extension on OSX, you will need to ensure that you do so in 64bit mode, otherwise you will receive an error message saying the extension could not be loaded, despite it compiling successfully. See the instructions at http://codelemur.wordpress.com/2009/05/30/pecl-memcache-and-php-on-mac-os-x-leopard/ for more details.

### Using the API

There are two basic scenarios for obtaining authorisation to act on behalf of a user against the Entrecard API:

1.  The Consumer (You) wish to act on behalf of another User, for example you wish to send money from their account to another user.
2.  The Consumer wishes to act on their own behalf, for example you may wish to send money from your account to another user.

In the first scenario, it is necessary initially to obtain permission from the User to act on their behalf. In the second, you can use a shortcut to avoid that need since you're asking for permission to act for yourself.

#### Step 1, Obtaining your Consumer key and secret

In order to identify yourself to the API, you need a Consumer key and secret. For Entrecard, your Consumer key is your User ID, and your secret is your Payments API key. You can obtain both these values by logging into your Entrecard dashboard, clicking on Payments API, and then clicking Enable. The User ID is just a number, the secret is a long string of hex.

#### Step 2, Obtaining a Request Token

A Request Token is a token+secret associated with a particular request for permissions. In short, it's a stepping stone to getting the Access Token, which is what you actually need in order to be able to make changes. A real-world metaphor is a birth certificate - you can't get on a plane with a birth certificate, but you can use it to get a passport which will let you get on the plane.

First up we set up with `OAuthEntrecard` object:

    $oa = OAuthEntrecard($your_consumer_key, $your_consumer_secret);
    
Then we obtain our unathorized request token:

    list($request_token, $request_token_secret) = $oa->getToken(array("authenticate","balance","withdraw"), $my_callback_url);
    
The array contains the specific permissions we want the user to grant us. `authenticate` is the lowest permission, and simply lets you verify they can identify themselves, gives you their user id and lets you obtain information like their email address and profile image.

`balance` allows you to obtain their current credit balance.

`withdraw` allows you to move credits from their account to any other Entrecard account.

`$my_callback_url` is the URL you'd like the User to be redirected to once they have granted permission for the token. More on exactly what that needs to handle later.

#### Step 3, Authorizing the Request Token

In order get your request token authorized, you need the User to grant permission. You can get the User to do this by sending them to a specific URL, which can be obtained like this:

    $authentication_url = $oa->getAuthenticationURL($request_token, $request_token_secret);

Simply redirect the User to that URL, they will be asked to log in (if they aren't already) and then shown a page indicating who you (The Consumer) is, and what permissions you want. The User can then decline or accept. If they accept, they will be redirected to the `$my_callback_url` you set in Step 2, with some information added.

#### Step 4, Getting the Access Token

Whether the User declines or accepts, in either case they are sent back to the `$my_callback_url`. You can quickly test whether they have declined by checking to see whether the oauth_token parameter was provided:

    if (!$_GET['oauth_token']) {
        // If there's no token, we didn't get the accept
        echo "Sorry, you declined us, so we can't proceed.";
        exit(0);
    }

If they haven't declined, we now have sufficient information to obtain an Access Token. This is the passport we referred to earlier, and allows you direct access to the API:

        list($user_id, $access_token, $access_token_secret) = $oa->getAccessToken($request_token, $request_token_secret, $_GET['oauth_verifier']);

Note that you will have had to store `$request_token` and `$request_token_secret` somewhere, either in a database or the session. You can see a demonstration of storing the information in the session in the test_authenticate.php/test_get_balance.php demonstration.

The callback receives two GET parameters, `$_GET['oauth_token']` and `$_GET['oauth_verifier]`.

#### Step 5, Accessing the API

Now that you have the Access Token, you're all ready to go. Note that the Access Token is valid for 24 hours, so you can save it somewhere to avoid the User having to keep granting you permission. Entrecard may extend that time later (Twitter, for example, which uses the same basic API, has an unlimited lifetime on their tokens).

In order to use the API, we create an Authority object:
    
    $eca = $oa->getAuthority($user_id, $access_token, $access_token_secret);

The Authority object has a bunch of convenient methods for obtaining Entrecard information. For example, we can get the email address of the user:

    echo $eca->getUserEmail();
    
Assuming you have asked for the `balance` permission, you can obtain the User balance:

    echo $eca->getBalance();

#### Step 6, Transferring credits

Transferring credits is a two step process. First you *prepare* the transfer, then you *commit* it. The reason for this is that often there is some logic in an application between needing to know the user has the credits available, and actually needing to transfer them.

*Warning:* You should NOT use the `getBalance()` method to ensure the User has sufficient funds for a task, otherwise there is a risk that they will spend credits elsewhere in-between your check of the balance and you actually withdrawing the funds. Instead, use a *prepare*, since this not only ensures they have the funds biut puts the funds aside temporarily (30 seconds) so that they cannot be spent before ready to *commit*.

To prepare a transfer of 10 credits from the User:

    $prepare_id = $eca->prepareTransferCredits(10)
    
to commit the transfer:

    $new_balance = $eca->commitTransferCredits($prepare_id, $to_user_id, "arbitrary short note");

The `$new_balance` is the new balance for the User who sent the credits. The balance will never be negative.

#### Giving credits to a user

Giving credits to a User is as simple as logging into the OAuth API as yourself, and then using the above transfer method to send to their user id. Logging in as yourself (the Consumer) is simpler than getting authorization from another user. You skip step 3 entirely, and get the verifier code like this:

    $verifier = $oa->consumerAuthenticate($request_token);

Then proceed as normal.

#### Questions

If you have any questions regarding the API at the moment, please contact Richard Clark <richard@redspider.co.nz>