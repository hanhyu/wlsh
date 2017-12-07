<?php
namespace Wlsh\W;
use Wlsh\S\DI;
class Login {
    public function index($request){
        return 'login action' ;
    }
    public function redis($request){
        return 'redis is key: '. DI::getInstance('redis')->get('key');
    }


}
