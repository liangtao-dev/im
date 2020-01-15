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
 * Class Message
 * @package TaoGe\Im\validate
 */
class Message extends Validate
{

    protected $rule = [
        'type|会话类型'             => 'require|in:1,2,3',
        'session_id|会话ID'       => 'require|number',
        'system_type_id|系统消息类型' => 'number',
        'from_id|消息发送方'         => 'require',
        'to_id|消息接收方'           => 'require',
        'msg_random|消息随机数'      => 'require|number',
        'msg_type_id|消息类型ID'    => 'require|in:1,2,3,4',
        'msg_content|消息内容'      => 'require',
        'list_rows|每页条数'        => 'number',
        'page|页码'               => 'number',
    ];

    protected $scene = [
        'pullMessage' => ['type', 'from_id', 'to_id', 'msg_random', 'msg_type', 'msg_content', 'system_type_id'],
        'pushMessage' => ['session_id', 'list_rows', 'page'],
        'updateRead'  => ['type', 'from_id', 'system_type_id'],
    ];

}
