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
// | Version: 2.0 2020-01-14 13:49
// +----------------------------------------------------------------------

namespace TaoGe\Im;


class Config
{
    /**
     * 配置
     * @var array
     */
    private $_config = [];

    /**
     * 实例
     * @var self|null
     */
    private static $_instance = null;

    /**
     * Config constructor.
     * @param array|null $config
     */
    private function __construct(?array $config)
    {
        if (is_array($config) && !empty($config)) {
            $this->_config = $config;
        }
    }

    /**
     * instance
     * @param array|null $config
     * @return self
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 14:03
     */
    public static function instance(?array $config = null)
    {
        if (self::$_instance === null) {
            self::$_instance = new self($config);
        }
        return self::$_instance;
    }

    /**
     * 读取配置
     * @param string $name
     * @return array|mixed|null
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 14:03
     */
    public function get(string $name = '')
    {
        if (!empty($name) && isset($this->_config[$name])) {
            return $this->_config[$name];
        }
        return $this->_config;
    }

    /**
     * 设置配置
     * @param string $name
     * @param        $value
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 14:05
     */
    public function set(string $name, $value)
    {
        $this->_config[$name] = $value;
    }

    /**
     * __clone
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 15:55
     */
    private function __clone(){}
}
