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
use TaoGe\Im\validate\Notice as Validate;
use think\Exception;
use think\db\exception\{DbException, DataNotFoundException, ModelNotFoundException};
use think\exception\ValidateException;


/**
 * Class Notice
 * @package TaoGe\Im\logic
 */
class Notice extends Logic
{

    /**
     * 推送
     * @param array                   $params
     * @param string                  $key
     * @param swoole_websocket_server $ws
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-12-16 11:11
     */
    public function push(array $params, string $key, swoole_websocket_server &$ws)
    {
        try {
            validate(Validate::class)->scene('push')->check($params);
        } catch (ValidateException $e) {
            $this->error = $e->getError();
            return false;
        }
        if ($params['key'] !== $key) {
            $this->error = '推送秘钥错误';
            return false;
        }
        $data   = [
            'type'           => 3,
            'system_type_id' => $params['system_type_id'],
            'from_id'        => $params['user_id'],
            'to_id'          => $params['object_id'],
            'msg_random'     => $params['msg_random'],
            'msg_type'       => $params['msg_type'],
            'msg_content'    => $params['msg_content']
        ];
        $logic  = new  Message;
        $result = $logic->pullMessage($data, $ws);
        if (!$result) {
            $this->error = $logic->getError();
            return false;
        }
        $this->result = $logic->getResult();
        return true;
    }

}
