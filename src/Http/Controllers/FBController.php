<?php namespace Vis\Registration;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;

use Cartalyst\Sentinel\Laravel\Facades\Sentinel;
use Cartalyst\Sentinel\Laravel\Facades\Activation;


class FBController extends Controller
{
    public function __construct()
    {
        Session::put('url_previous', URL::previous());
    }

    public function doLogin()
    {
        $link = route('auth_fb_res');
        header("Location: $link");
    }

    //auth FB
    public function index()
    {
        $app_id = Config::get('registration.social.fb.api_id');;
        $app_secret = Config::get('registration.social.fb.secret_key');
        $my_url = url("/") . "/auth_soc/face_res";
        $code = Input::get("code");
        $state = Input::get("state");

        if (empty($code)) {

            Session::put('state', md5(uniqid(rand(), TRUE)));
            $dialog_url = "http://www.facebook.com/dialog/oauth?client_id="
                . $app_id . "&redirect_uri=" . urlencode($my_url) . "&scope=public_profile,email&state="
                .  Session::get('state')."&fields=email,first_name,last_name,id,gender";
            header("Location: $dialog_url");
        }

        if ($state == Session::get('state')) {
            $token_url = "https://graph.facebook.com/oauth/access_token?"
                . "client_id=" . $app_id . "&redirect_uri=" . urlencode($my_url)
                . "&client_secret=" . $app_secret . "&code=" . $code."&fields=email,first_name,last_name,id,gender";

            $response = file_get_contents($token_url);
            $params = null;
            parse_str($response, $params);
            $graph_url = "https://graph.facebook.com/me?access_token=". $params['access_token']."&fields=email,first_name,last_name,id,gender";
            $user = json_decode(file_get_contents($graph_url));

            $firstName = $user->first_name;
            $lastName = $user->last_name;

            $fbId = $user->id;

            if (isset($user->email)) {
                $userEmail = $user->email;
            } else {
                $userEmail = $fbId;
            }

            //check user
            if ($userEmail && $fbId) {

                $user = DB::table("users")->where("id_fb", $fbId)->first();
                if (!$user['id']) {
                    $user = DB::table("users")->where("email", "like", $userEmail)->first();
                }

                if (!$user['id']) {
                    $randomPassword = str_random(8);
                    $user = Sentinel::registerAndActivate(array(
                        'email'    => $userEmail,
                        'password' => $randomPassword,
                        'first_name' => $firstName,
                        'last_name' => $lastName
                    ));
                    $user->id_fb = $fbId;
                    $user->save();

                    $userAuth = Sentinel::findById($user->id);
                    Sentinel::login($userAuth, Config::get('registration.social.fb.remember'));

                } else {
                    $userAuth = Sentinel::findById($user['id']);
                    Sentinel::login($userAuth, Config::get('registration.social.fb.remember'));
                }
                $redirect = Session::get('url_previous', "/");
                Session::forget('url_previous');

                //if not empty redirect_url
                if (Config::get('registration.social.fb.redirect_url')) {
                    $redirect = Config::get('registration.social.fb.redirect_url');
                    Session::flash('id_user', $userAuth->id);
                }else {
                    $redirect = Session::get('url_previous', "/");
                    Session::forget('url_previous');
                }

                return Redirect::to($redirect);
            }
        }
    }
}

