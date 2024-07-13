<?php
// +----------------------------------------------------------------------
// | ThinkPHP Socket Gateway [Socket Gateway Service For ThinkPHP]
// +----------------------------------------------------------------------
// | ThinkPHP Socket Gateway 服务
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: axguowen <axguowen@qq.com>
// +----------------------------------------------------------------------

namespace think\socket\gateway;

class Service extends \think\Service
{
    /**
     * 注册服务
     * @access public
     * @return void
     */
    public function register()
    {
        // 设置命令
        $this->commands([
            'socket:gateway' => Command::class,
        ]);
    }
}
