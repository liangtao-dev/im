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

use think\db\exception\{DbException, DataNotFoundException, ModelNotFoundException};
use Exception;
use TaoGe\Im\model\{
    ImGroupsMsgUser,
    ImGroupsUser,
    ImGroupsMsgContent
};


/**
 * 群消息
 * @package TaoGe\Im\logic
 */
class Groups extends Logic
{
    /**
     * 消息存储
     * @param array $params
     * @return bool
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 9:57
     */
    public function storeMessage(array $params)
    {
        $model    = new ImGroupsMsgContent;
        $group_id = $params['to_id'];
        unset($params['to_id']);
        $insert              = $params;
        $insert['from_name'] = get_group_nickname($params['from_id'], $group_id);
        $insert['group_id']  = $group_id;
        $model->data($insert)->save();
        $group_msg_id = $model->getLastInsID();
        $result       = ImGroupsUser::where(['group_id' => $group_id])->field('group_id,user_id')->select()->toArray();
        if (!empty($result) && is_array($result)) {
            $insertAll = [];
            $model     = new ImGroupsMsgUser;
            $time      = time();
            foreach ($result as &$value) {
                $insertAll[] = [
                    'group_id'     => $group_id,
                    'group_msg_id' => $group_msg_id,
                    'user_id'      => $value['user_id'],
                    'state'        => 0,
                    'create_time'  => $time,
                    'update_time'  => $time
                ];
            }
            $model->saveAll($insertAll);
            return true;
        }
        $this->error = '群里一个人也没有';
        return false;
    }

    /**
     * 获取聊天记录
     * @param string $user_id
     * @param array  $params
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 10:03
     */
    public function getMessages(string $user_id, array $params)
    {
        ['id' => $session_id, 'to_id' => $group_id, 'list_rows' => $list_rows, 'page' => $page, 'type' => $type] = $params;
        $this->result = [
            'session_id' => $session_id, // 会话ID
            'type'       => $type,// 会话类型
            'to'         => get_by_group_id($group_id),
            'totalRows'  => 0,  // 总行数
            'firstRow'   => 0,  // 起始行数
            'totalPages' => 0,  // 分页总页面数
            'nowPage'    => 0,  // 当前页数
            'unread'     => 0,  // 未读消息
            'list'       => []  // 聊天记录
        ];
        $where        = ['user_id' => $user_id, 'group_id' => $group_id];
        $list         = ImGroupsMsgUser::where($where)->order('id DESC')->page($page, $list_rows)->select()->toArray();
        if (!empty($list) && is_array($list)) {
            $ImGroupsMsgContentModel = new  ImGroupsMsgContent;
            foreach ($list as &$value) {
                if ($value['state'] === 0) { // 未读消息
                    $this->result['unread']++;
                }
                $msg_content = $ImGroupsMsgContentModel->where(['id' => $value['group_msg_id']])->find();
                if (!empty($msg_content)) {
                    $msg_content['from'] = get_by_uuid($msg_content['from_id']);
                }
                $value['msg_content'] = $msg_content;
            }
            $count        = ImGroupsMsgUser::where($where)->count();
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
     * @param int    $group_id
     * @return bool
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-12 12:09
     */
    public function updateReadState(string $user_id, int $group_id)
    {
        $where['state']    = 0;
        $where['user_id']  = $user_id;
        $where['group_id'] = $group_id;
        ImGroupsMsgUser::update(['state'=> 1],$where);
        return true;
    }

}
