<?php namespace Drhuy\Extendsuser;

use System\Classes\PluginBase;

class Plugin extends PluginBase
{

    public $require = ['RainLab.User'];

    public function registerComponents()
    {
    	return [
    		'Drhuy\Extendsuser\Components\FormsAuth'=> 'form_auth'
    	];
    }

    public function registerSettings()
    {
    }

    function boot() {

        \RainLab\User\Models\User::extend(function($model){
        	$model->bindEvent('model.beforeValidate', function() use ($model) {
	        
        		$model-> customMessages = [
        			'*.unique' 			=> ':attribute đã tồn tại',
        			'*.between'			=> ':attribute phải từ :min đến :max ký tự',
                    '*.required_with'   => ':attribute không trùng khớp',
                    '*.same'            => ':attribute không trùng khớp',
        			'*.email'			=> ':attribute không phải là email'
        		];

        	});

        });
    }
}
