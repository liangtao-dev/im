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

/**
 * Class Result
 * @package TaoGe\Im\logic
 */
class Result
{
    /**
     * 返回成功信息
     * @param string       $action
     * @param string|array $data
     * @param string       $msg
     * @return false|string
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-11-28 11:46
     */
    public static function success(string $action, $data, $msg = 'success')
    {
        $result = [
            'action' => $action,
            'code'   => 0,
            'msg'    => $msg,
            'data'   => $data
        ];
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 返回失败信息
     * @param string       $action
     * @param string       $msg
     * @param string|array $data
     * @return false|string
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2019-11-28 11:46
     */
    public static function error(string $action, $msg = 'fail', $data = '')
    {
        $result = [
            'action' => $action,
            'code'   => 1,
            'msg'    => $msg,
            'data'   => $data
        ];
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

}
