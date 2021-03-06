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
 * Class Server
 * @package TaoGe\Im\validate
 */
class Server extends Validate
{

    protected $rule = [
        'user_id|用户ID' => 'require',
        'action|行为标识'  => 'require',
    ];

}
