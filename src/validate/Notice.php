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
namespace TaoGe\Im\validate;

/**
 * Class Notice
 * @package TaoGe\Im\validate
 */
class Notice extends Validate
{

    protected $rule = [
        'key|推送秘钥'            => 'require',
        'system_type_id|系统类型' => 'require|number',
        'user_id|消息发送者'       => 'require',
        'object_id|接收消息者'     => 'require|number',
        'msg_type_id|消息类型'    => 'require|number',
        'msg_content|消息内容'    => 'require',
        'msg_random|消息随机数'    => 'require',
    ];

    protected $scene = [
        'push' => ['key', 'system_type_id', 'user_id', 'object_id', 'msg_type_id', 'msg_content', 'msg_random'],
    ];
}
