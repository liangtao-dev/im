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
 * Class Logic
 * @package TaoGe\Im\logic
 */
abstract class Logic
{
    protected $error = '';
    protected $result = '';

    public function getError()
    {
        return $this->error;
    }

    public function getResult()
    {
        return $this->result;
    }

}
