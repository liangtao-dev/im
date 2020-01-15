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
use PDOException;
use Swoole\WebSocket\Server;
use Swoole\Http\{Request, Response};
use TaoGe\Im\validate\Server as Validate;
use TaoGe\Im\logic\{Login, Message, Result, Notice};

/**
 * Class Event
 * @package TaoGe\Im
 */
class Event
{

    /**
     * http请求处理
     * @param Request  $request
     * @param Response $response
     * @param string   $pushSecurityKey
     * @param Server   $webSocket
     * @return bool|void
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 13:00
     */
    public static function request(Request &$request, Response &$response, string &$pushSecurityKey, Server &$webSocket)
    {
        // 跨域OPTIONS返回
        $response->header('Access-Control-Allow-Origin', '*');
        // 返回JSON格式
        $response->header('Content-Type', 'application/json; charset=UTF-8');
        if ($request->server['request_method'] == 'OPTIONS') {
            $response->status(http_response_code());
            $response->end();
            return;
        }
        $logic  = new Notice;
        $res    = $logic->push((array)$request->post, $pushSecurityKey, $webSocket);
        $result = ['code' => 0, 'msg' => $logic->getResult()];
        if (!$res) {
            $result = ['code' => 1, 'msg' => $logic->getError()];
        }
        $response->end(json_encode($result));
    }

    /**
     * 消息路由事件
     * @param Server                  $ws
     * @param                         $frame
     * @return bool
     * @throws Exception
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:41
     */
    public static function message(Server &$ws, $frame)
    {
        if (empty($frame->data) || !is_json($frame->data)) {
            return $ws->push($frame->fd, Result::error('exception', '数据格式异常', $frame->data));
        }
        $params = json_decode($frame->data, true);
        try {
            validate(Validate::class)->check($params);
        } catch (ValidateException $e) {
            return $ws->push($frame->fd, Result::error('exception', $e->getError()));
        }
        ['user_id' => $user_id, 'action' => $action, 'params' => $data] = $params;
        switch ($action) {
            case 'login': // 客户端推送登录消息过来
                $logic  = new Login;
                $result = $logic->login($user_id, $frame->fd);
                break;
            case 'logout': // 客户端推送退出消息过来
                $logic  = new Login;
                $result = $logic->logout($frame->fd, $user_id);
                break;
            case 'pull_session': // 客户端拉取会话记录
                $logic  = new Message;
                $result = $logic->pushSession($user_id);
                break;
            case 'send_message': // 客户端推送聊天消息过来
                $logic  = new Message;
                $result = $logic->pullMessage($data, $ws);
                break;
            case 'pull_message': // 客户端拉取聊天记录
                $logic  = new Message;
                $result = $logic->pushMessage($user_id, $data);
                break;
            case 'update_read': // 更新消息状态为已读
                $logic  = new Message;
                $result = $logic->updateRead($user_id, $data, $ws);
                break;
            default:
                return $ws->push($frame->fd, Result::error($action, 'action错误'));
        }
        if (!$result) {
            return $ws->push($frame->fd, Result::error($action, $logic->getError()));
        }
        return $ws->push($frame->fd, Result::success($action, $logic->getResult()));
    }

    /**
     * WebSocket连接关闭事件
     * @param Server $ws
     * @param int    $fd
     * @return bool
     * @throws Exception
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-10 11:40
     */
    public static function close(Server &$ws, int $fd)
    {
        unset($ws);
        return (new Login)->logout($fd);
    }

    /**
     * Swoole服务重新启动时,清除因为上次异常关闭导致的异常数据
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019/12/14 11:31
     */
    public static function clearOnlineUsers()
    {
        $dbConfig = Config::instance()->get('dbConfig');
        $sql      = 'TRUNCATE `' . $dbConfig['prefix'] . 'im_online`';
        Db::connect()->query($sql);
    }

}
