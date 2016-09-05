<?php namespace Vis\Registration;

use Cartalyst\Sentinel\Laravel\Facades\Activation;
use Cartalyst\Sentinel\Laravel\Facades\Sentinel;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Vis\MailTemplates\MailT;

class RegistrationController extends Controller
{
    /**
     * @var array rules registration
     */
    public $reg_rules
        = array (
            'email' => 'required|email|unique:users',
            'password' => 'required|min:5'
        );

    /**
     * @var array rules authorization
     */
    public $auth_rules
        = array (
            'email' => 'required|email',
            'password' => 'required|min:5'
        );

    /**
     * @var array rules forgot password
     */
    public $forgot_rules
        = array (
            'email' => 'required'
        );

    public $messages
        = array (
            'first_name.required' => 'Поле имя должно быть заполнено',
            'password.min' => 'Поле пароль должно быть минимум 5 знаков',
            'password.required' => 'Поле пароль должно быть заполнено',
            'email.unique' => 'Пользователь с данным Email уже существует',
            'email.email' => 'Не правильный формат email',
            'email.required' => 'Поле email должно быть заполнено'
        );

    /*
     * Authorization
     */
    public function doLogin ()
    {
        parse_str (Input::get ('filds'), $filds);

        $validator = Validator::make ($filds, $this->auth_rules);
        if ($validator->fails ()) {

            return Response::json (
                array (
                    'status' => 'error',
                    "errors_messages" => implode ("<br>",
                        $validator->messages ()->all ())
                )
            );
        }

        try {
            $user = Sentinel::authenticate (
                array (
                    'email' => $filds['email'],
                    'password' => $filds['password'],
                )
            );

            if ($user) {
                return Response::json (
                    array (
                        'status' => 'ok',
                        "ok_messages" => "Вы успешно авторизованы"
                    )
                );

            } else {
                return Response::json (
                    array (
                        'status' => 'error',
                        "errors_messages" => "Пользователь не найден"
                    )
                );
            }

        } catch (\Cartalyst\Sentinel\Checkpoints\NotActivatedException $e) {

            return Response::json (
                array (
                    'status' => 'error',
                    "errors_messages" => "Пользователь не активирован"
                )
            );
        }
    } //end doLogin

    /**
     * Registration on site
     *
     * @return mixed
     */
    public function doRegistration ()
    {
        parse_str (Input::get ('filds'), $filds);

        //check password
        if ($filds['password'] != $filds['re_password']) {
            return Response::json (
                array (
                    'status' => 'error',
                    "errors_messages" => "Ошибка. Пароли не совпадают"
                )
            );
        }

        $validator = Validator::make ($filds, $this->reg_rules,
            $this->messages);

        if ($validator->fails ()) {
            return Response::json (
                array (
                    'status' => 'error',
                    "errors_messages" => implode ("<br>",
                        $validator->messages ()->all ())
                )
            );
        }
        try {

            $fields = [
                'email' => $filds['email'],
                'password' => $filds['password'],
            ];

            if (is_array (Config::get ('registration.registration.field_for_registration'))) {

                foreach (
                    Config::get ('registration.registration.field_for_registration')
                    as $fieldBd => $fieldUser
                ) {
                    if (isset($filds[$fieldUser])) {
                        $fields[$fieldBd] = $filds[$fieldUser];
                    }
                }
            }

            $user = Sentinel::register (
                $fields
            );

            $activation = Activation::create ($user);

            $mail
                = new MailT(Config::get ('registration.registration.template_mail'),
                [
                    "login" => $filds['email'],
                    "password" => $filds['password'],
                    "activation_url" => route ('activating_user',
                        ["id" => $user->id, "token" => $activation->getCode ()])
                ]);
            $mail->to = $filds['email'];

            if (Config::get ('registration.registration.no_write_bd') == true) {
                $mail->no_write_bd = true;
            }

            $mail->send ();

            return Response::json (
                array (
                    "status" => "ok",
                    "ok_messages" => "Вы успешно зарегистрированы. На почту выслана ссылка для активации",
                )
            );

        } catch (\Cartalyst\Sentinel\Users\UserExistsException $e) {
            return Response::json (
                array (
                    'status' => 'error',
                    "errors_messages" => $this->messages['email.unique'],
                )
            );
        }


    } //end doRegistration

    /*
     * logout
     */
    public function doLogout ()
    {
        Sentinel::logout ();

        return Redirect::back ();
    } //end doLogout

    /**
     * forgot pass, send new password
     *
     * @return mixed
     */
    public function doForgotPass ()
    {
        parse_str (Input::get ('filds'), $filds);

        $validator = Validator::make ($filds, $this->forgot_rules,
            $this->messages);
        if ($validator->fails ()) {
            return Response::json (
                array (
                    'status' => 'error',
                    "errors_messages" => implode ("<br>",
                        $validator->messages ()->all ())
                )
            );
        }

        $user = Sentinel::findByCredentials (["login" => $filds['email']]);

        if ($user) {
            $newPassword = str_random (7);
            Sentinel::update ($user, array ('password' => $newPassword));

            $mail = new MailT(Config::get ('registration.forgot_pass.template_mail'),
                [
                    "name_user" => $user->first_name,
                    "new_password" => $newPassword
                ]);

            $mail->to = $filds['email'];

            if (Config::get ('registration.forgot_pass.no_write_bd') == true) {
                $mail->no_write_bd = true;
            }

            $mail->send ();

            return Response::json (
                array (
                    'status' => 'ok',
                    "ok_messages" => "Вам на почту был выслан новый пароль"
                )
            );
        } else {

            return Response::json (
                array (
                    'status' => 'error',
                    "errors_messages" => "Пользователь не найден"
                )
            );
        }
    }

    /**
     * activation user
     *
     * @param $id    id user
     * @param $token token code for activation
     *
     * @return mixed
     */

    public function doActivatingUser ($id, $token)
    {
        $user = Sentinel::findById ($id);

        if ($activation = Activation::completed ($user)) {
            $result = "Пользователь уже активирован ";
            $status = "activation_completed";
            Sentinel::login ($user);

            return View::make ('registration::activating_user',
                compact ("result", "status"));
        } else {

            if (Activation::complete ($user, $token)) {

                $result = "Пользователь активирован";
                $status = "success";
                Sentinel::login ($user);

                return View::make ('registration::activating_user',
                    compact ("result", "status"));
            } else {
                $result = "Ошибка. Пользователя код активации не подходит";
                $status = "error";

                return View::make ('registration::activating_user',
                    compact ("result", "status"));
            }
        }

    }
}