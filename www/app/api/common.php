<?php

namespace App\API;

class common extends \App\API\Base\JsonRPC
{


    //получение  токена
    public function token($args) {


        $api = \App\System::getOptions('api');

        $user = \App\Helper::login($args['login'], $args['password']);

        if ($user instanceof \App\Entity\User) {
            $key = strlen($api['key']) > 0 ? $api['key'] : "defkey";
            $exp = strlen($api['exp']) > 0 ? $api['exp'] : 60;

            $token = array(
                "user_id" => $user->user_id,
                "iat"     => time(),
                "exp"     => time() + $exp * 60
            );

            $jwt = \Firebase\JWT\JWT::encode($token, $key);
        } else {
            throw new \Exception(\App\Helper::l('invalidlogin'), -1000);
        }

        return $jwt;
    }

    public function checkapi() {
        return "OK";
    }


    //список  производственных участвков
    public function parealist() {
        $list = \App\Entity\ProdArea::findArray('pa_name', '', 'pa_name');

        return $list;
    }

    //список  компаний
    public function firmlist() {
        $list = \App\Entity\Firm::findArray('firm_name', 'disabled<>1', 'firm_name');

        return $list;
    }

    //список  источников  продаж
    public function sourcelist() {
        $common = \App\System::getOptions('common');
        $list = array();
        foreach ($common['salesources'] as $s) {
            $list[$s->id] = $s->name;
        };

        return $list;
    }


}
