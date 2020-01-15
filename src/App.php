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
// | Version: 2.0 2019-11-26 14:21
// +----------------------------------------------------------------------
namespace TaoGe\Im;

use Exception;
use Swoole\WebSocket\Server;
use Swoole\Http\{Request, Response};
use TaoGe\Im\logic\Result;

/**
 * Class App
 * @package TaoGe\Im
 */
class App
{
    // 全局配置信息
    private $_config = [
        // WebSocket服务端口
        'port'            => 9502,
        // 进程守护
        'daemonize'       => 0,
        // WebSocket服务协议
        'protocol'        => 'wss',
        // 通知推送秘钥
        'pushSecurityKey' => 'uyzSPfViYejprFJPEzcvxsPuDRzZ6KUXgrRwqmvr',
        // SSL证书
        'ssl'             => [
            'ssl_key_file'  => '../assets/ssl/privkey.key',
            'ssl_cert_file' => '../assets/ssl/fullchain.crt',
        ],
        // 数据库信息
        'dbConfig'        => [
            // 主机地址
            'host'      => '127.0.0.1',
            // 用户名
            'user'      => 'root',
            // 密码
            'password'  => '',
            // 数据库名
            'database'  => 'demo',
            // 端口
            'port'      => 3306,
            // 数据库表前缀
            'prefix'    => 'abon_',
            // 连接超时
            'timeout'   => 0.5,
            // 数据库编码
            'charset'   => 'utf8mb4',
            // 连接池大小
            'pool_size' => '3',
        ],
    ];

    // 数据库配置信息
    private $_dbConfig = [];

    // webSocket服务端口
    private $_port = null;

    // webSocket服务协议
    private $_protocol = null;

    // webSocket服务器对象
    private $_webSocket = null;

    // 通知推送的安全秘钥
    private $_pushSecurityKey = null;

    // SSL证书
    private $_ssl = [];

    // 守护进程
    private $_daemonize = 0;

    /**
     * App constructor.
     * @param array $config
     */
    public function __construct(?array $config = null)
    {
        if (is_array($config) && !empty($config)) {
            $this->_config = array_merge($this->_config, $config);
            $this->setConfig($this->_config);
            $this->setDbConfig($this->_dbConfig);
        }
    }

    /**
     * 运行
     * @throws Exception
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 10:52
     */
    public function run()
    {
        $config = ['daemonize' => $this->getDaemoniz()];
        if ($this->getProtocol() === 'wss') {
            $ssl_key = [
                'ssl_key_file'  => $this->getSsl('ssl_key_file'),
                'ssl_cert_file' => $this->getSsl('ssl_cert_file'),
            ];
            $config  = array_merge($config, $ssl_key);
            // 创建websocket服务器对象，监听0.0.0.0:9502端口
            $this->_webSocket = new Server("0.0.0.0", $this->_port, SWOOLE_BASE, SWOOLE_SOCK_TCP | SWOOLE_SSL);
        } else {
            $this->_webSocket = new Server("0.0.0.0", $this->_port);
        }
        $this->_webSocket->set($config);

        // 监听WebSocket连接打开事件
        $this->_webSocket->on('open', function (Server $ws, $request) {
            echo "server: handshake success with fd{$request->fd}\n";
            $ws->push($request->fd, Result::success('open', '', '连接成功'));
        });

        // 监听Request消息事件
        $this->_webSocket->on('request', function (Request $request, Response $response) {
            Event::request($request, $response, $this->_pushSecurityKey, $this->_webSocket);
        });

        // 监听WebSocket消息事件
        $this->_webSocket->on('message', function ($ws, $frame) {
            echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
            Event::message($ws, $frame);
        });

        // 监听WebSocket连接关闭事件
        $this->_webSocket->on('close', function ($ws, $fd) {
            echo "client-{$fd} is closed\n";
            Event::close($ws, $fd);
        });

        // 启动MySQL进程池
        echo '启动MySQL进程池' . PHP_EOL;
        MysqlPool::instance($this->_dbConfig);
        // 清除异常的成员在线数据
        echo '清除异常数据' . PHP_EOL;
        Event::clearOnlineUsers();
        // 启动webSocket服务
        echo '启动webSocket服务' . PHP_EOL;
        $this->_webSocket->start();
    }

    /**
     * 设置全局配置
     * @param array $config
     * @return $this
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 11:59
     */
    public function setConfig(array $config)
    {
        $this->_config = array_merge($this->_config, $config);
        foreach ($this->_config as $key => $value) {
            if (isset($this->{'_' . $key})) {
                $this->{'_' . $key} = $value;
                Config::instance()->set($key, $value);
            }
        }
        return $this;
    }

    /**
     * 设置数据库配置
     * @param array $db_config
     * @return $this
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 11:00
     */
    public function setDbConfig(array $db_config)
    {
        $this->setConfig(['dbConfig' => $db_config]);
        return $this;
    }

    /**
     * 设置SSL证书
     * @param string $ssl_key_file
     * @param string $ssl_cert_file
     * @return $this
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 12:11
     */
    public function setSsl(string $ssl_key_file, string $ssl_cert_file)
    {
        $this->setConfig(['ssl' => ['ssl_key_file' => $ssl_key_file, 'ssl_cert_file' => $ssl_cert_file]]);
        return $this;
    }

    /**
     * 设置推送服务的安全秘钥
     * @param string $key
     * @return $this
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 11:14
     */
    public function setPushSecurityKey(string $key)
    {
        $this->setConfig(['pushSecurityKey' => $key]);
        return $this;
    }

    /**
     * 设置WebSocket端口
     * @param int $port
     * @return $this
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 11:12
     */
    public function setPort(int $port)
    {
        $this->setConfig(['port' => $port]);
        return $this;
    }

    /**
     * 设置WebSocket服务协议
     * @param string $protocol
     * @return $this
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 11:49
     */
    public function setProtocol(string $protocol)
    {
        $this->setConfig(['protocol' => $protocol]);
        return $this;
    }

    /**
     * 设置守护进程
     * @param bool $value
     * @return $this
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 13:27
     */
    public function setDaemoniz(bool $value)
    {
        $this->setConfig(['daemonize' => $value ? 1 : 0]);
        return $this;
    }

    /**
     * 获取守护进程
     * @return int
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 13:27
     */
    public function getDaemoniz()
    {
        return $this->_daemonize;
    }

    /**
     * 获取SSl证书
     * @param string $name
     * @return array
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 12:11
     */
    public function getSsl(string $name = '')
    {
        if (!empty($name) && isset($this->_ssl[$name])) {
            return $this->_ssl[$name];
        }
        return $this->_ssl;
    }

    /**
     * 获取全局配置
     * @param string $name
     * @return array
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 11:59
     */
    public function getConfig(string $name = '')
    {
        if (!empty($name) && isset($this->_config[$name])) {
            return $$this->_config[$name];
        }
        return $this->_config;
    }

    /**
     * 获取数据库配置
     * @param string $name
     * @return array|null
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 10:27
     */
    public function getDbConfig(string $name = '')
    {
        if (!empty($name) && isset($this->_dbConfig[$name])) {
            return $$this->_dbConfig[$name];
        }
        return $this->_dbConfig;
    }

    /**
     * 获取WebSocket端口
     * @return int
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 11:13
     */
    public function getPort()
    {
        return $this->_port;
    }

    /**
     * 获取推送服务的安全秘钥
     * @return string
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 11:15
     */
    public function getPushSecurityKey()
    {
        return $this->_pushSecurityKey;
    }

    /**
     * 获取WebSocket服务协议
     * @return string
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 11:45
     */
    public function getProtocol()
    {
        return $this->_protocol;
    }

    /**
     * 获取WebSocket服务
     * @param $ws
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-14 11:17
     */
    public function getWebSocket(&$ws)
    {
        $ws = $this->_webSocket;
    }
}
