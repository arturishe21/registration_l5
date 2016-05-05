<?php namespace Vis\Registration;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Cartalyst\Sentinel\Laravel\Facades\Sentinel;
use Cartalyst\Sentinel\Laravel\Facades\Activation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Vis\Builder\User;

class VKController extends Controller
{
    public function __construct()
    {
        Session::put('url_previous', URL::previous());
    }

    public function doLogin()
    {
        $destination = "http://api.vk.com/oauth/authorize?client_id=".Config::get('registration.social.vk.api_id')."&scope=friends,photos,offline&display=popup&redirect_uri=".route('auth_vk_res');
        header("Location: $destination");
    }

    /**
     * @return mixed
     */
    public function index()
    {
        if (Input::get("code")) {

            $apiId = Config::get('registration.social.vk.api_id');
            $secretKey = Config::get('registration.social.vk.secret_key');

            $params = array(
                'client_id' => $apiId,
                'client_secret' => $secretKey,
                'code' => Input::get("code"),
                'redirect_uri' => url("/") . "/auth_soc/vk_res"
            );

            $url = 'https://oauth.vk.com/access_token' . '?' . urldecode(http_build_query($params));
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($result, true);

            if (isset($data['access_token'])) {
                $str = "https://api.vkontakte.ru/method/getProfiles?uid=" . $data['user_id'] . "&fields=photo_big,email&access_token=" . $data['access_token'];
                $resp2 = file_get_contents($str);
                $el = json_decode($resp2, true);

                $firstName = $el['response'][0]['first_name'];
                $lastName = $el['response'][0]['last_name'];
                $idUser = $el['response'][0]['uid'];

                $user = DB::table("users")->where("id_vk", $idUser)->first();

                if (!isset($user['id'])) {

                    $randomPassword = str_random(8);

                    $user = Sentinel::registerAndActivate(array(
                        'email'    => $idUser,
                        'password' => $randomPassword,
                        'first_name' => $firstName,
                        'last_name' => $lastName
                    ));
                    $user->id_vk = $idUser;
                    $user->save();

                    //load avatar user
                    $this->loadSaveAvatar($user, $el);

                    Sentinel::login($user, Config::get('registration.social.vk.remember'));

                } else {
                    $userAuth = Sentinel::findById($user['id']);
                    Sentinel::login($userAuth, Config::get('registration.social.vk.remember'));
                }

                //if not empty redirect_url
                if (Config::get('registration.social.vk.redirect_url')) {
                    $redirect = Config::get('registration.social.vk.redirect_url');
                    Session::flash('id_user', $userAuth->id);
                } else {
                    $redirect = Session::get('url_previous', "/");
                    Session::forget('url_previous');
                }

                return Redirect::to($redirect);
            }
        }
    }

    /**
     * load and save avatar user
     *
     * @param $user User
     * @param $avatar string
     */
    private function loadSaveAvatar($user, $avatar)
    {
        if (isset($avatar['response'][0]['photo_big'])
            && $avatar['response'][0]['photo_big']
            && Config::get('registration.social.vk.foto')) {

            $avatarPath = $avatar['response'][0]['photo_big'];
            $destinationPath = "/storage/tb-users/avatars/";

            $pathPictures = public_path().$destinationPath;
            File::makeDirectory($pathPictures, 0777, true, true);

            file_put_contents (
                $pathPictures.basename($avatarPath),
                file_get_contents($avatarPath)
            );
            $user->image = $destinationPath.basename($avatarPath);
            $user->save();
        }
    }
}