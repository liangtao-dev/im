<?php
// +----------------------------------------------------------------------
// | CodeEngine
// +----------------------------------------------------------------------
// | Copyright 艾邦
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: TaoGe <liangtao.gz@foxmail.com>
// +----------------------------------------------------------------------
// | Version: 2.0 2019-11-27 22:18
// +----------------------------------------------------------------------
namespace TaoGe\Im\logic;

use Redis;

/**
 * Class MyRedis
 * @package TaoGe\Im\logic
 */
class MyRedis extends Redis
{
    private static $_instance = '';

    public function __construct()
    {
        parent::__construct();
        $this->connect('127.0.0.1', 6379);
        $this->auth('uSog1Pm9');
    }

    public static function getInstance()
    {
        if (!self::$_instance instanceof MyRedis) {
            self::$_instance = new MyRedis;
        }
        return self::$_instance;
    }

}
