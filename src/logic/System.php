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

use Exception;
use TaoGe\Im\model\{ImSystem, ImSystemMsgUser};
use think\db\exception\{DbException, DataNotFoundException, ModelNotFoundException};

/**
 * Class System
 * @package TaoGe\Im\logic
 */
class System extends Logic
{
    /**
     * 消息存储
     * @param $params
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-17 13:39
     */
    public function storeMessage($params)
    {
        $model     = new ImSystem;
        $object_id = $params['to_id'];
        unset($params['to_id']);
        $insert              = $params;
        $insert['object_id'] = $object_id;
        $model->data($insert)->save();
        $system_msg_id = $model->getLastInsID();
        $users         = [];
        if (!get_by_object_id($object_id, $users)) {
            $this->error = '系统消息的接收群体人数为0';
            return false;
        }
        $time      = time();
        $insertAll = [];
        foreach ($users as &$value) {
            $insertAll[] = [
                'system_type_id' => $params['system_type_id'],
                'user_id'        => $value['uuid'],
                'system_msg_id'  => $system_msg_id,
                'state'          => 0,
                'create_time'    => $time,
                'update_time'    => $time
            ];
            unset($value);
        }
        $ImSystemMsgUserModel = new ImSystemMsgUser;
        $ImSystemMsgUserModel->saveAll($insertAll);
        unset($dataList);
        return true;
    }

    /**
     * 获取聊天记录
     * @param string $user_id
     * @param array  $params
     * @return bool
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:32
     */
    public function getMessages(string $user_id, array $params)
    {
        ['id' => $session_id, 'list_rows' => $list_rows, 'page' => $page, 'system_type_id' => $system_type_id, 'type' => $type] = $params;
        $this->result = [
            'session_id' => $session_id, // 会话ID
            'type'       => $type,
            'to'         => get_by_system_type_id($system_type_id),
            'totalRows'  => 0,  // 总行数
            'firstRow'   => 0,  // 起始行数
            'totalPages' => 0,  // 分页总页面数
            'nowPage'    => 0,  // 当前页数
            'unread'     => 0,  // 未读消息
            'list'       => []  // 聊天记录
        ];
        $where        = ['user_id' => $user_id, 'system_type_id' => $system_type_id];
        $list         = ImSystemMsgUser::where($where)->order('id DESC')->page($page . ',' . $list_rows)->select()->toArray();
        if (!empty($list)) {
            foreach ($list as &$value) {
                if ($value['state'] === 0) { // 未读消息
                    $this->result['unread']++;
                }
                $msg_content = ImSystem::where(['id' => $value['system_msg_id']])->find();
                if ($msg_content !== null) {
                    $msg_content['from'] = get_by_uuid($msg_content['from_id']);
                }
                $value['msg_content'] = $msg_content;
            }
            $count        = ImSystemMsgUser::where($where)->count();
            $Page         = new Page($count, $list_rows);
            $this->result = array_merge($this->result, [
                'totalRows'  => $count,             // 总行数
                'firstRow'   => $Page->firstRow,    // 起始行数
                'totalPages' => $Page->totalPages,  // 分页总页面数
                'nowPage'    => $Page->nowPage,     // 当前页数
                'list'       => $list
            ]);
        }
        return true;
    }

    /**
     * 更新消息为已读状态
     * @param string $user_id
     * @param int    $system_type_id
     * @return bool
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-12 12:09
     */
    public function updateReadState(string $user_id, int $system_type_id)
    {
        $where['state']          = 0;
        $where['user_id']        = $user_id;
        $where['system_type_id'] = $system_type_id;
        ImSystemMsgUser::update(['state' => 1], $where);
        return true;
    }

}
