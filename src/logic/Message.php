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

use swoole_websocket_server;
use think\db\Query;
use think\Exception;
use think\db\exception\{DbException, DataNotFoundException, ModelNotFoundException};
use TaoGe\Im\validate\Message as Validate;
use TaoGe\Im\model\{
    ImGroups,
    ImGroupsMsgUser,
    ImMessages,
    Im,
    ImGroupsUser,
    ImGroupsMsgContent,
    ImSystem,
    ImSystemMsgUser,
    ImSystemObject,
    ImSystemType
};
use think\exception\ValidateException;

/**
 * 消息
 * @package TaoGe\Im\logic
 */
class Message extends Logic
{
    // 默认页码
    protected $defaultPage = 1;
    // 默认每页多少条
    protected $defaultListRows = 25;

    /**
     * 推送聊天记录到客户端
     * @param string $user_id
     * @param array  $params
     * @return bool
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:16
     */
    public function pushMessage(string $user_id, array $params)
    {
        try {
            validate(Validate::class)->scene('pushMessage')->check($params);
        } catch (ValidateException $e) {
            $this->error = $e->getError();
            return false;
        }
        $params['page']      = (isset($params['page']) && !empty($params['page'])) ? $params['page'] : $this->defaultPage;
        $params['list_rows'] = (isset($params['list_rows']) && !empty($params['list_rows'])) ? $params['list_rows'] : $this->defaultListRows;
        ['session_id' => $session_id, 'page' => $page, 'list_rows' => $list_rows] = $params;
        $session = Im::where(['id' => $session_id])->find();
        if ($session === null) {
            $this->error = '无效的会话ID';
            return false;
        }
        $session = $session->toArray();
        ['type' => $type, 'to_id' => $to_id, 'from_id' => $from_id] = $session;
        $params       = array_merge($params, $session);
        $this->result = [
            'session_id' => $session_id, // 会话ID
            'type'       => $type,  // 会话类型
            'to'         => null,   // 对方信息
            'totalRows'  => 0,      // 总行数
            'firstRow'   => 0,      // 起始行数
            'totalPages' => 0,      // 分页总页面数
            'nowPage'    => 0,      // 当前页数
            'unread'     => 0,      // 未读消息
            'list'       => []      // 聊天记录
        ];
        switch ((int)$type) {
            case 1: // 单聊
                $list = ImMessages::where(function (Query $query) use ($from_id, $to_id) {
                    $query->where(['from_id' => $from_id, 'to_id' => $to_id]);
                })->whereOr(function (Query $query) use ($from_id, $to_id) {
                    $query->where(['from_id' => $to_id, 'to_id' => $from_id]);
                })->order('id DESC')->page($page . ',' . $list_rows)->select()->toArray();
                if (!empty($list) && is_array($list)) {
                    foreach ($list as &$value) {
                        if ($value['state'] === 0 && $value['to_id'] === $user_id) { // 未读消息
                            $this->result['unread']++;
                        }
                        $value['from'] = get_by_uuid($value['from_id']);
                        $value['to']   = get_by_uuid($value['to_id']);
                    }
                    $count        = ImMessages::where(function (Query $query) use ($from_id, $to_id) {
                        $query->where(['from_id' => $from_id, 'to_id' => $to_id]);
                    })->whereOr(function (Query $query) use ($from_id, $to_id) {
                        $query->where(['from_id' => $to_id, 'to_id' => $from_id]);
                    })->count();
                    $Page         = new Page($count, $list_rows);
                    $this->result = array_merge($this->result, [
                        'to'         => get_by_uuid($user_id === $from_id ? $to_id : $from_id),    //对方信息
                        'totalRows'  => $count,                 // 总行数
                        'firstRow'   => $Page->firstRow,        // 起始行数
                        'totalPages' => $Page->totalPages,      // 分页总页面数
                        'nowPage'    => $Page->nowPage,         // 当前页数
                        'list'       => $list
                    ]);
                }
                break;
            case 2: // 群聊
                $logic = new Groups;
                if (!$logic->getMessages($user_id, $params)) {
                    $this->error = $logic->getError();
                    return false;
                }
                $this->result = $logic->getResult();
                break;
            case 3: // 系统
                $logic = new System;
                if (!$logic->getMessages($user_id, $params)) {
                    $this->error = $logic->getError();
                    return false;
                }
                $this->result = $logic->getResult();
                break;
            default:
                $this->error = '不支持的消息类型';
                return false;
        }
        return true;
    }

    /**
     * 推送会话列表到客户端
     * @param string $user_id
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:31
     */
    public function pushSession(string $user_id)
    {
        $this->result = [];
        $group_ids    = [];
        $result       = ImGroupsUser::where(['user_id' => $user_id])->field('user_id,group_id')->select()->toArray();
        if (!empty($result) && is_array($result)) {
            foreach ($result as $value) {
                $group_ids[] = $value['group_id'];
            }
        }
        $system = Im::where(['type' => 3])->order('update_time DESC')->select()->toArray();
        if (!empty($group_ids)) { // 如果加入群组的话,调群组会话记录
            $map_1['to_id'] = ['in', implode(',', $group_ids)];
            $map_1['type']  = 2;
            $result         = Im::where(function (Query $query) use ($user_id) {
                $query->where(function (Query $query) use ($user_id) {
                    $query->where(['from_id' => $user_id, 'type' => 1]);
                })->whereOr(function (Query $query) use ($user_id) {
                    $query->where(['to_id' => $user_id, 'type' => 1]);
                });
            })->whereOr(function (Query $query) use ($group_ids) {
                $query->where(['to_id' => ['in', implode(',', $group_ids)], 'type' => 2]);
            })->order('update_time DESC')->select()->toArray();
        } else {
            $result = Im::where(function (Query $query) use ($user_id) {
                $query->where(function (Query $query) use ($user_id) {
                    $query->where(['from_id' => $user_id, 'type' => 1]);
                })->whereOr(function (Query $query) use ($user_id) {
                    $query->where(['to_id' => $user_id, 'type' => 1]);
                });
            })->order('update_time DESC')->select()->toArray();
        }
        if (!empty($result) && is_array($result)) {
            foreach ($result as &$value) {
                self::analysis($value, $user_id);
            }
            $unread_key = array_column($result, 'unread');
            array_multisort($unread_key, SORT_DESC, $result);
            if (!empty($system) && is_array($system)) {
                foreach ($system as &$value) {
                    self::analysis($value, $user_id);
                }
                $result = array_merge($system, $result);
            }
            $this->result = $result;
        }
        return true;
    }

    /**
     * 拉取客户端聊天信息
     * @param array                   $params
     * @param swoole_websocket_server $ws
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:28
     */
    public function pullMessage(array $params, swoole_websocket_server &$ws)
    {
        $validate = new Validate;
        if (!$validate->scene('pullMessage')->check($params)) {
            $this->error = $validate->getError();
            return false;
        }

        // 记录会话
        $result = $this->autoSession($params['type'], $params['from_id'], $params['to_id'], isset($params['system_type_id']) ? $params['system_type_id'] : null);
        if (!$result) return false;

        // 存储消息
        $result = $this->storeMessage($params['type'], $params);
        if (!$result) return false;

        // 更新客户端这条消息相关用户的会话列表
        (new ActivePush)->sessionAll($params, $ws);

        return true;
    }

    /**
     * 更新消息状态为已读
     * @param string                  $user_id
     * @param array                   $params
     * @param swoole_websocket_server $ws
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:28
     */
    public function updateRead(string $user_id, array $params, swoole_websocket_server &$ws)
    {
        $validate = new Validate;
        if (!$validate->scene('updateRead')->check($params)) {
            $this->error = $validate->getError();
            return false;
        }
        $params['system_type_id'] = (isset($params['system_type_id']) && !empty('system_type_id')) ? $params['system_type_id'] : 0;
        ['from_id' => $from_id, 'type' => $type, 'system_type_id' => $system_type_id] = $params;
        switch ($type) {
            case 1: // 单聊
                $where['state']   = 0;
                $where['to_id']   = $user_id;
                $where['from_id'] = $from_id;
                ImMessages::update(['state' => 1], $where);
                $session_id = Im::where(function (Query $query) use ($from_id, $user_id) {
                    $query->where(['from_id' => $from_id, 'to_id' => $user_id]);
                })->whereOr(function (Query $query) use ($from_id, $user_id) {
                    $query->where(['from_id' => $user_id, 'to_id' => $from_id]);
                })->value('id');
                if (!$session_id) {
                    $this->error = '无效的会话';
                    return false;
                }
                // 更新消息发送者客户端该条信息为已读状态
                $ActivePush = new ActivePush;
                $result     = $ActivePush->message($from_id, $session_id, $ws);
                if (!$result) {
                    var_dump($ActivePush->getError());
                }
                break;
            case 2: // 群聊
                $logic = new Groups;
                if (!$logic->updateReadState($user_id, $from_id)) {
                    $this->error = $logic->getError();
                    return false;
                }
                break;
            case 3: // 系统
                $logic = new System;
                if (!$logic->updateReadState($user_id, $system_type_id)) {
                    $this->error = $logic->getError();
                    return false;
                }
                break;
            default:
                $this->error = '不支持的消息类型';
                return false;
        }
        // 更新客户端登录用户本人的会话列表
        (new ActivePush)->session($user_id, $ws);
        return true;
    }

    /**
     * 自动创建会话记录,如果存在则更新会话记录
     * @param int      $type
     * @param string   $from_id
     * @param string   $to_id
     * @param int|null $system_type_id
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-09 17:06
     */
    private function autoSession(int $type, string $from_id, string $to_id, ?int $system_type_id = null)
    {
        $insert = ['type' => $type];
        $update = ['type' => $type, 'update_time' => time()];
        switch ($type) {
            case 1: // 单聊
                if (!get_by_uuid($to_id)) {
                    $this->error = 'to_id不存在';
                    return false;
                }
                Im::where(function (Query $query) use ($from_id, $to_id, $type) {
                    $query->where(['from_id' => $from_id, 'to_id' => $to_id, 'type' => $type]);
                })->whereOr(function (Query $query) use ($from_id, $to_id, $type) {
                    $query->where(['from_id' => $to_id, 'to_id' => $from_id, 'type' => $type]);
                });
                $insert['from_id'] = $from_id;
                $insert['to_id']   = $to_id;
                $update            = $insert;
                break;
            case 2: // 群聊
                if (!ImGroups::where(['id' => $to_id])->count()) {
                    $this->error = 'group_id不存在';
                    return false;
                }
                Im::where(function (Query $query) use ($to_id, $type) {
                    $query->where(['to_id' => $to_id, 'type' => $type]);
                });
                $insert['from_id'] = $from_id;
                $insert['to_id']   = $to_id;
                $update['from_id'] = $from_id;
                break;
            case 3: // 系统
                if (!ImSystemObject::where(['id' => $to_id])->count()) {
                    $this->error = 'object_id不存在';
                    return false;
                }
                if ($system_type_id !== null) {
                    if (!ImSystemType::where(['id' => $system_type_id])->count()) {
                        $this->error = 'system_type_id不存在';
                        return false;
                    }
                } else {
                    $system_type_id = 1;// 默认1:系统公告
                }
                Im::where(function (Query $query) use ($system_type_id, $type) {
                    $query->where(['system_type_id' => $system_type_id, 'type' => $type]);
                });
                $insert['from_id']        = $from_id;
                $insert['to_id']          = $to_id;
                $insert['system_type_id'] = $system_type_id;
                $update['from_id']        = $from_id;
                break;
            default:
                $this->error = '会话类型异常';
                return false;
        }
        $session_id = Im::value('id');
        if ($session_id) {
            Im::update($update, ['id' => $session_id]);
        } else {
            $model = new Im;
            $model->data($insert)->save();
        }
        return true;
    }

    /**
     * 存储消息内容及发送队列到数据库
     * @param int   $type
     * @param array $params
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:29
     */
    private function storeMessage(int $type, array $params)
    {
        unset($params['type']);
        switch ($type) {
            case 1: // 单聊
                $model           = new ImMessages;
                $insert          = $params;
                $insert['state'] = 0;
                $model->data($insert)->save();
                break;
            case 2: // 群聊
                $logic = new Groups;
                if (!$logic->storeMessage($params)) {
                    $this->error = $logic->getError();
                    return false;
                }
                break;
            case 3: // 系统
                $logic = new System;
                if (!$logic->storeMessage($params)) {
                    $this->error = $logic->getError();
                    return false;
                }
                break;
            default:
                $this->error = '不支持的消息类型';
                return false;
        }
        return true;
    }

    /**
     * 解析数据
     * @param array  $value
     * @param string $user_id
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:31
     */
    private static function analysis(array &$value, string $user_id)
    {
        $value['from'] = get_by_uuid($value['from_id']);
        switch ($value['type']) {
            case 1:
                $result = ImMessages::where(function (Query $query) use ($user_id) {
                    $query->where(['from_id' => $user_id,]);
                })->whereOr(function (Query $query) use ($user_id) {
                    $query->where(['to_id' => $user_id,]);
                })->order('id DESC')->find();
                if ($result !== null) {
                    $result = $result->toArray();
                }
                $value['body']   = $result;
                $value['to']     = get_by_uuid($value['to_id']);
                $value['unread'] = ImMessages::where(['to_id' => $user_id, 'state' => 0])->count();
                break;
            case 2:
                $where  = ['group_id' => $value['to_id']];
                $result = ImGroupsMsgContent::where($where)->order('id DESC')->find();
                if ($result !== null) {
                    $result = $result->toArray();
                }
                $value['body']   = $result;
                $value['to']     = get_by_group_id($value['to_id']);
                $value['unread'] = ImGroupsMsgUser::where(['group_id' => $value['to_id'], 'user_id' => $user_id, 'state' => 0])->count();
                break;
            case 3:
                $where  = ['system_type_id' => $value['system_type_id']];
                $result = ImSystem::where($where)->order('id DESC')->find();
                if ($result !== null) {
                    $result = $result->toArray();
                }
                $value['body']   = $result;
                $value['to']     = get_by_system_type_id($value['system_type_id']);
                $value['unread'] = ImSystemMsgUser::where(['system_type_id' => $value['system_type_id'], 'user_id' => $user_id, 'state' => 0])->count();
                break;
        }
    }

}
