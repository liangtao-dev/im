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
// | Version: 2.0 2020-01-15 15:08
// +----------------------------------------------------------------------

namespace TaoGe\Im;

use PDO;
use PDOException;
use RuntimeException;
use Swoole\Coroutine\Channel;

/**
 * MySQL进程池
 * @package TaoGe\Im
 */
class MysqlPool
{
    private static $instance;
    private $pool;  //连接池容器，一个channel
    private $config;

    /**
     * @param null $config
     * @return $this
     * @desc   获取连接池实例
     */
    public static function instance($config = null)
    {
        if (empty(self::$instance)) {
            if (empty($config)) {
                throw new RuntimeException("mysql config empty");
            }
            self::$instance = new static($config);
        }

        return self::$instance;
    }

    /**
     * MysqlPool constructor.
     * @param $config
     * @desc  初始化，自动创建实例,需要放在workerstart中执行
     */
    private function __construct($config)
    {
        if (empty($this->pool)) {
            $this->config = $config;
            $this->pool   = new Channel($config['pool_size']);
            // 默认这个不是长连接，如果需要数据库长连接，需要最后加一个参数：array(PDO::ATTR_PERSISTENT => true) 变成这样：
            $options = array(PDO::ATTR_PERSISTENT => true);
            $dsn     = "mysql:host={$config['host']};dbname={$config['database']}";
            for ($i = 0; $i < $config['pool_size']; $i++) {
                try {
                    // 初始化一个PDO对象
                    $mysql = new PDO($dsn, $config['user'], $config['password'], $options);
                    // mysql连接存入channel
                    go(function () use ($mysql) {
                        $this->pool->push($mysql);
                    });
                } catch (PDOException $e) {
                    die($e->__toString() . PHP_EOL);
                }
            }
            echo '创建PDO连接：' . $this->pool->length() . PHP_EOL;
        }
    }

    /**
     * @param $mysql
     * @desc  放入一个mysql连接入池
     */
    public function put($mysql)
    {
        $this->pool->push($mysql);
    }

    /**
     * @return mixed
     * @desc   获取一个连接，当超时，返回一个异常
     */
    public function get()
    {
        $mysql = $this->pool->pop();
        if (false === $mysql) {
            throw new RuntimeException("get mysql timeout, all mysql connection is used");
        }
        return $mysql;
    }

    /**
     * @return mixed
     * @desc   获取当时连接池可用对象
     */
    public function getLength()
    {
        return $this->pool->length();
    }

    private function __clone()
    {
    }
}
