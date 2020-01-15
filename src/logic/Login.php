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

use TaoGe\Im\validate\Login as Validate;
use TaoGe\Im\model\ImOnline as Model;
use think\db\exception\{DbException, DataNotFoundException, ModelNotFoundException};
use Exception;
use think\exception\ValidateException;

/**
 * Class Login
 * @package TaoGe\Im\logic
 */
class Login extends Logic
{
    /**
     * 登录
     * @param string $user_id
     * @param int    $fd
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-09 16:29
     */
    public function login(string $user_id, int $fd)
    {
        $data     = ['user_id' => $user_id, 'fd' => $fd];
        try {
            validate(Validate::class)->check($data);
        } catch (ValidateException $e) {
            $this->error = $e->getError();
            return false;
        }
        $user = get_by_uuid($user_id);
        if (!$user) {
            $this->error = '用户不存在';
            return false;
        }
        if (Model::where($data)->count()) {
            Model::update(['update_time' => time()], $data);
            $this->result = $user;
            return true;
        }
        $model = new Model;
        $model->data($data)->save();
        $this->result = $user;
        return true;
    }

    /**
     * logout
     * @param int    $fd
     * @param string $user_id
     * @return bool
     * @throws Exception
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2020-01-09 16:32
     */
    public function logout(int $fd, string $user_id = '')
    {
        $data = ['user_id' => $user_id, 'fd' => $fd];
        if (empty($user_id)) {
            unset($data['user_id']);
        }
        try {
            validate(Validate::class)->check($data);
        } catch (ValidateException $e) {
            $this->error = $e->getError();
            return false;
        }
        Model::where($data)->delete();
        $this->result = '您已安全退出';
        return true;
    }
}
