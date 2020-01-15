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
// | Version: 2.0 2019-12-13 16:43
// +----------------------------------------------------------------------

namespace TaoGe\Im\logic;

use swoole_websocket_server;
use TaoGe\Im\model\ImGroupsUser;
use think\db\exception\{DbException, DataNotFoundException, ModelNotFoundException};
use think\Exception;

/**
 * 主动推送信息到客户端
 * @package TaoGe\Im\logic
 */
class ActivePush extends logic
{
    /**
     * 更新指定用户的指定会话的最新聊天内容
     * @param string                  $user_id
     * @param int                     $session_id
     * @param swoole_websocket_server $ws
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:27
     */
    public function message(string $user_id, int $session_id, swoole_websocket_server &$ws)
    {
        $fds = is_online($user_id, $ws);
        if (!$fds) {
            $this->error = $user_id . '用户没有在线';
            return false;
        }
        $logic  = new Message;
        $result = $logic->pushMessage($user_id, ['session_id' => $session_id]);
        if (!$result) {
            $this->error = $logic->getError();
            return false;
        }
        foreach ($fds as $fd) {
            $ws->push($fd, Result::success('pull_message', $logic->getResult()));
        }
        return true;
    }

    /**
     * 更新单个指定用户的会话列表
     * @param string                  $user_id
     * @param swoole_websocket_server $ws
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:34
     */
    public function session(string $user_id, swoole_websocket_server &$ws)
    {
        $fds = is_online($user_id, $ws);
        if (!$fds) {
            $this->error = $user_id . '没有在线的链接';
            return false;
        }
        $logic  = new Message;
        $result = $logic->pushSession($user_id);
        if (!$result) {
            $this->error = $logic->getError();
            return false;
        }
        foreach ($fds as $fd) {
            $ws->push($fd, Result::success('pull_session', $logic->getResult()));
        }
        return true;
    }

    /**
     * 更新消息相关所有人员的会话列表
     * @param array                   $params
     * @param swoole_websocket_server $ws
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:34
     */
    public function sessionAll(array $params, swoole_websocket_server &$ws)
    {
        ['type' => $type, 'to_id' => $to_id] = $params;
        switch ($type) {
            case 1: // 单聊
                $user_id = $to_id;
                $this->session($user_id, $ws);
                break;
            case 2: // 群聊
                $group_id = $to_id;
                $model    = new ImGroupsUser;
                $result   = $model->field('group_id,user_id')->where(['group_id' => $group_id])->select();
                if (empty($result) || !is_array($result)) {
                    $this->error = '群里没人';
                    return false;
                }
                foreach ($result as $value) {
                    $this->session($value['user_id'], $ws);
                }
                break;
            case 3: // 系统
                $object_id = $to_id;
                $users     = [];
                if (!get_by_object_id($object_id, $users)) {
                    $this->error = '系统消息的接收群体人数为0';
                    return false;
                }
                foreach ($users as &$value) {
                    $user_id = $value['uuid'];
                    unset($value);
                    $this->session($user_id, $ws);
                }
                break;
            default:
                $this->error = '不支持的消息类型';
                return false;
        }
        return true;
    }
}
