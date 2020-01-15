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
// | Version: 2.0 2020-01-15 16:20
// +----------------------------------------------------------------------

namespace TaoGe\Im;

use PDO;
use PDOException;
use PDOStatement;

class Db
{

    /**
     * 实例
     * @var self|null
     */
    private static $_instance = null;

    /**
     * mysql
     * @var PDO
     */
    public $mysql;

    /**
     * Db constructor.
     */
    private function __construct()
    {

    }

    /**
     * instance
     * @return self
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 14:03
     */
    public static function instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * connect
     * @return Db
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-15 16:44
     */
    public static function connect()
    {
        go(function () {
            try {
                $pool                    = MysqlPool::instance();
                self::instance()->mysql = $pool->get();
                echo "当前可用连接数：" . $pool->getLength() . PHP_EOL;
                defer(function () {
                    //利用defer特性，可以达到协程执行完成，归还$mysql到连接池
                    //好处是 可能因为业务代码很长，导致乱用或者忘记把资源归还
                    MysqlPool::instance()->put(self::instance()->mysql);
                    echo "当前可用连接数：" . MysqlPool::instance()->getLength() . PHP_EOL;
                });
            } catch (PDOException  $e) {
                die($e->__toString() . PHP_EOL);
            }
        });
        return self::instance();
    }

    /**
     * query
     * @param string $sql
     * @return false|PDOStatement
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-15 16:37
     */
    public function query(string $sql)
    {
        return $this->mysql->query($sql);
    }

    /**
     * __clone
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 15:55
     */
    private function __clone()
    {
    }

}
