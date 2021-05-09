<?php namespace Drhuy\Extendsuser\Components;

use Cms\Classes\ComponentBase;
use Mail;
use Redirect;
use Validator;
use ValidationException;
use Cms\Classes\Page;
use RainLab\User\Facades\Auth;
use RainLab\User\Models\User as UserModel;

class FormsAuth extends ComponentBase
{

    public $formId;

    public $code;

    public function componentDetails()
    {
        return [
            'name'        => 'Forms Authenticate',
            'description' => 'Render Form Authenticate'
        ];
    }

    public function defineProperties()
    {
        return [
            'code'=> [
                'title'         => 'Code',
                'description'   => 'Code for Active account or reset password',
                'type'          => 'text',
                'default'       => '{{ :code }}'
            ],
            'redirect'=> [
                'title'         => 'Redirect to',
                'type'          => 'dropdown',
                'default'       => ''
            ]
        ];
    }

    public function getRedirectOptions()
    {
        return [''=>'- refresh page -', '0' => '- no redirect -'] + Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun(){

        $this-> formId = 'auth/'. $this-> property('formId') != ''? $this-> property('formId'): 'login';
        if(!$this-> formId)
            $this-> formId = 'login';
        $this-> formId = 'auth/'. $this-> formId;

        $this-> code   = $this-> property('code');
    }

    public function onSignin(){
        try {
            $user = Auth::authenticate([
                'login'     => post('login'),
                'password'  => post('password')
            ]);
            if($user)
                return $this-> makeRedirection();
        } catch (\October\Rain\Auth\AuthException $e) {
            $authMessage = $e->getMessage();
            if (strrpos($authMessage,'not activated') !== false) {
                $message = 'Tài khoản chưa Active';
            } else {
                $message = 'Sai tài khoản hoặc mật khẩu!';
            }
            \Flash::error($message);
        }
    }

    public function onRegister(){
        $user = Auth::register([
            'name'      => post('name'),
            'username'  => post('username'),
            'email'     => post('email'),
            'password'  => post('password'),
            'password_confirmation' => post('password_confirmation'),
        ], true);

        if($user){
            Auth::login($user);
            return $this-> makeRedirection();
        }
        return Flash::error("Sai tài khoản hoặc mật khẩu");

    }

    public function onRestorePassword(){
        $rules = [
            'email' => 'required|email|between:6,255'
        ];

        $validation = Validator::make(post(), $rules);
        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        $user = UserModel::findByEmail(post('email'));
        if (!$user || $user->is_guest) {
            throw new \ApplicationException("Không tìm thấy email này!");
        }

        $code = implode('!', [$user->id, $user->getResetPasswordCode()]);

        $link = $this->makeResetUrl($code);

        $data = [
            'name' => $user->name,
            'username' => $user->username,
            'link' => $link,
            'code' => $code
        ];

        Mail::send('rainlab.user::mail.restore', $data, function($message) use ($user) {
            $message->to($user->email, $user->full_name);
        });
        \Flash::success("Đã gởi link khôi phục vào email của bạn. Vui lòng kiểm tra email!");
    }

    public function onResetPassword(){

        $rules = [
            'code'        => 'required',
            'password'    => 'required|between:' . UserModel::getMinPasswordLength() . ',255',
            'password_confirmation' => 'same:password'
        ];

        $messages = [
            '*.required'        => ':attribute không được bỏ trống!',
            '*.same'            => ':attribute không trùng khớp',
            '*.between'         => ':attribute phải từ :min đến :max ký tự!'
        ];

        $validation = Validator::make(post(), $rules, $messages);
        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        $errorFields = ['code' => 'Bạn đang cố gắn tìm tài khoản của người khác?'];

        $parts = explode('!', post('code'));
        if (count($parts) != 2) {
            throw new ValidationException($errorFields);
        }

        list($userId, $code) = $parts;

        if (!strlen(trim($userId)) || !strlen(trim($code)) || !$code) {
            throw new ValidationException($errorFields);
        }

        if (!$user = Auth::findUserById($userId)) {
            throw new ValidationException($errorFields);
        }

        if (!$user->attemptResetPassword($code, post('password'))) {
            throw new ValidationException($errorFields);
        }
        if($user){
            Auth::login($user);
            return $this-> makeRedirection();
        }
    }

    protected function makeRedirection($intended = false)
    {
        $method = $intended ? 'intended' : 'to';

        $property = trim((string) $this->property('redirect'));

        // No redirect
        if ($property === '0') {
            return;
        }
        // Refresh page
        if ($property === '') {
            return Redirect::refresh();
        }

        $redirectUrl = $this->pageUrl($property) ?: $property;

        if ($redirectUrl = post('redirect', $redirectUrl)) {
            return Redirect::$method($redirectUrl);
        }
    }

    /**
     * Returns a link used to reset the user account.
     * @return string
     */
    protected function makeResetUrl($code)
    {
        $params = [
            $this->property('paramCode') => $code
        ];

        if ($pageName = $this->property('resetPage')) {
            $url = $this->pageUrl($pageName, $params);
        }
        else {
            $url = $this->currentPageUrl($params);
        }

        if (strpos($url, $code) === false) {
            $url = "$url/$code";
        }

        return $url;
    }

}
