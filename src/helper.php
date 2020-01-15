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
// | Version: 2.0 2019-11-28 11:27
// +----------------------------------------------------------------------

!defined('RESOURCE_ROOT') && define('RESOURCE_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR);


use think\db\exception\{DbException, DataNotFoundException, ModelNotFoundException};
use  TaoGe\Im\model\{
    ImOnline as ImOnlineModel,
    ImGroups as ImGroupsModel,
    ImSystemType as ImSystemTypeModel,
    ImGroupsUser as ImGroupsUserModel
};

/**
 * 是否为json字符串
 * @param $string
 * @return bool
 * @author TaoGe <liangtao.gz@foxmail.com>
 * @date   2019-12-03 11:49
 */
function is_json(string $string)
{
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

/**
 * 获取用户默认头像
 * @param int $sex
 * @return string
 * @author TaoGe <liangtao.gz@foxmail.com>
 * @date   2019-12-11 10:49
 */
function get_user_default_avatar(int $sex)
{
    $default_path = RESOURCE_ROOT . 'images/avatar/';
    switch ($sex) {
        case 1: // 男
            return $default_path . 'boys.jpg';
            break;
        case 2: // 女
            return $default_path . 'girl.jpg';
            break;
        default: // 保密
            return $default_path . 'unknown.jpg';
    }
}

/**
 * 获取群组默认头像
 * @return string
 * @author TaoGe <liangtao.gz@foxmail.com>
 * @date   2019-12-11 10:54
 */
function get_group_default_avatar()
{
    return RESOURCE_ROOT . 'images/avatar/group.jpg';
}

/**
 * 获取系统消息默认头像
 * @return string
 * @author TaoGe <liangtao.gz@foxmail.com>
 * @date   2019-12-11 11:34
 */
function get_system_default_avatar()
{
    return RESOURCE_ROOT . 'images/avatar/system.jpg';
}

/**
 * 获取用户信息
 * @param string $user_id
 * @return array|bool
 * @throws DataNotFoundException
 * @throws ModelNotFoundException
 * @throws DbException
 * @author TaoGe <liangtao.gz@foxmail.com>
 * @date   2020-01-09 15:52
 */
function get_by_uuid(string $user_id)
{
    $where = ['uid' => $user_id];
    $user  = Member::where($where)->find();
    if ($user !== null) {
        $data = $user->toArray();
        if ($user->getData('avatar') === 0) {
            $data['avatar'] = get_user_default_avatar($data['sex']);
        }
        return $data;
    }
    return false;
}

/**
 * 获取系统消息用户
 * @param int   $object_id
 * @param array $users
 * @return bool
 * @throws DataNotFoundException
 * @throws DbException
 * @throws ModelNotFoundException
 * @author TaoGe <liangtao.gz@foxmail.com>
 * @date   2019-12-16 15:33
 */
function get_by_object_id(int $object_id, array &$users)
{
    switch ($object_id) {
        case 1: // 全部
            $users = Member::where('status', 1)->select()->toArray();
            break;
        case 2:
            // 待定义的用户用户群体类型
            break;
        default:
            return false;
    }
    if (empty($users) || !is_array($users)) {
        return false;
    }
    return true;
}

/**
 * 获取群信息
 * @param string $group_id
 * @return array|bool
 * @throws DataNotFoundException
 * @throws DbException
 * @throws ModelNotFoundException
 * @author TaoGe <liangtao.gz@foxmail.com>
 * @date   2019-12-16 15:33
 */
function get_by_group_id(string $group_id)
{
    $model  = new ImGroupsModel;
    $where  = ['id' => $group_id];
    $result = $model->where($where)->find();
    if ($result === null) {
        return false;
    }
    $group = $result->toArray();
    if ($group && empty($group['icon'])) {
        $group['icon'] = get_group_default_avatar();
    }
    return $group;
}

/**
 * 获取用户的群昵称
 * @param string $user_id
 * @param int    $group_id
 * @return mixed|string
 * @throws DataNotFoundException
 * @throws DbException
 * @throws ModelNotFoundException
 * @author TaoGe <liangtao.gz@foxmail.com>
 * @date   2019-12-16 15:33
 */
function get_group_nickname(string $user_id, int $group_id)
{
    $where      = ['group_id' => $group_id, 'user_id' => $user_id];
    $group_nick = ImGroupsUserModel::where($where)->value('group_nick');
    if (empty($group_nick)) { // 如果没有群昵称,则取本来的名称
        $user = get_by_uuid($user_id);
        if (!$user) {
            return '';
        }
        $group_nick = $user['name'];
    }
    return $group_nick;
}

/**
 * 获取系统消息类型
 * @param int $system_type_id
 * @return array|false
 * @throws DataNotFoundException
 * @throws DbException
 * @throws ModelNotFoundException
 * @author TaoGe <liangtao.gz@foxmail.com>
 * @date   2019-12-16 15:34
 */
function get_by_system_type_id(int $system_type_id)
{
    $where  = ['id' => $system_type_id];
    $result = ImSystemTypeModel::where($where)->find();
    if ($result === null) {
        return false;
    }
    $system_type = $result->toArray();
    if ($system_type && empty($system_type['icon'])) {
        $system_type['icon'] = get_system_default_avatar();
    }
    return $system_type;
}

/**
 * 用户是否在线
 * @param string                  $user_id
 * @param swoole_websocket_server $ws
 * @return array|bool
 * @throws DataNotFoundException
 * @throws DbException
 * @throws ModelNotFoundException
 * @throws Exception
 * @author TaoGe <liangtao.gz@foxmail.com>
 * @date   2019-12-16 15:34
 */
function is_online(string $user_id, swoole_websocket_server &$ws)
{
    $fds_connections = [];
    foreach ($ws->connections as $fd) {
        // 需要先判断是否是正确的websocket连接，否则有可能会push失败
        if ($ws->isEstablished($fd)) {
            array_push($fds_connections, $fd);
        }
    }
    $where = ['user_id' => $user_id];
    if (ImOnlineModel::where($where)->count()) {
        $result = ImOnlineModel::where($where)->field('fd')->select();
        $fds    = [];
        foreach ($result as $value) {
            if (in_array($value['fd'], $fds_connections)) {
                $fds[] = $value['fd'];
            } else { // 无效的在线记录从数据库中删除
                ImOnlineModel::where('fd', $value['fd'])->delete();
            }
        }
        return $fds;
    }
    return false;
}

if (!function_exists('lt')) {
    /**
     * 调试打印
     * @param mixed $value
     * @param bool  $stop
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-20 15:01
     */
    function lt($value,  $stop = true)
    {
        switch (gettype($value)) {
            case 'resource':
            case 'boolean':
                var_dump($value);
                break;
            case 'integer':
            case 'double':
            case 'string':
                echo($value);
                break;
            case 'object':
//                try {
//                    echo new Reflectionclass($value);
//                } catch (ReflectionException $e) {
//                    echo $e->getMessage();
//                }
//                break;
            case 'array':
                print_r($value);
                break;
            case 'NULL':
                echo 'NULL';
                break;
            default:
                echo 'unknown type';
        }
        echo "\r\n";
        $stop && exit;
    }
}
