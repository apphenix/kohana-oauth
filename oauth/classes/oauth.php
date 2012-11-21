<?php defined('SYSPATH') or die('No direct script access.');
class Oauth {
    protected static $instance;
    public static function instance($service) {
        $config = Kohana::$config -> load('oauth');
        $class = 'Oauth_' . ucfirst($service);
        return self::$instance = new $class($config -> get($service));
    }
}