<?php namespace Vis\Registration;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;

use Cartalyst\Sentinel\Laravel\Facades\Sentinel;
use Cartalyst\Sentinel\Laravel\Facades\Activation;

class GoogleController extends Controller
{

    public function google()
    {
        Session::put('url_previous', URL::previous());

        $url = 'https://accounts.google.com/o/oauth2/auth';

        $params = array(
            'redirect_uri'  => Config::get('registration.social.google.redirect_oauth2callback'),
            'response_type' => 'code',
            'client_id'     => Config::get('registration.social.google.api_id'),
            'scope'         => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile'
        );

        return Redirect::to($url . "?" . urldecode(http_build_query($params)));
    }

    //auth google
    public function oauth2callback()
    {
        if (Input::get("code")) {

            $params = array(
                'client_id' => Config::get('registration.social.google.api_id'),
                'client_secret' => Config::get('registration.social.google.secret_key'),
                'redirect_uri' => Config::get('registration.social.google.redirect_oauth2callback'),
                'grant_type' => 'authorization_code',
                'code' => Input::get("code")
            );

            $url = 'https://accounts.google.com/o/oauth2/token';

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, urldecode(http_build_query($params)));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($curl);
            curl_close($curl);

            $tokenInfo = json_decode($result, true);

            if (isset($tokenInfo['access_token'])) {
                $params['access_token'] = $tokenInfo['access_token'];

                $userInfo = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v1/userinfo' . '?' . urldecode(http_build_query($params))), true);

                if ($userInfo["id"]) {
                    $email = trim($userInfo['email']);
                    $user = DB::table("users")->where("email", "like", $email)->first();

                    if (!$user->id) {

                        $randomPassword = str_random(8);

                        $user =  Sentinel::registerAndActivate(array(
                            'email'    => $email,
                            'password' => $randomPassword,
                            'first_name'=>$userInfo['given_name'],
                            'last_name'=>$userInfo['family_name']
                        ));

                        $userAuth = Sentinel::findById($user->id);
                        Sentinel::login($userAuth, Config::get('registration.social.google.remember'));

                    } else {
                        $userAuth = Sentinel::findById($user->id);
                        Sentinel::login($userAuth, Config::get('registration.social.google.remember'));
                    }

                  
                    //if not empty redirect_url
                    if (Config::get('registration::social.google.redirect_url')) {
                        $redirect = Config::get('registration::social.google.redirect_url');
                        Session::flash('id_user', $userAuth->id);
                    } else {
                        $redirect = Session::get('url_previous', "/");
                        Session::forget('url_previous');
                    }

                    return Redirect::to($redirect);
                }
            }
        }
    }
}

