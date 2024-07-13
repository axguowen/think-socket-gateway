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

namespace think;

// 命令行入口文件
// 加载基础文件
require dirname(__DIR__, 3) . '/autoload.php';

// 如果命令不是 socket:gateway 则退出
if ($argc < 2 || $argv[1] != 'socket:gateway') {
    exit('Not Support Command: ' . $argv[1] . PHP_EOL);
}
// 应用初始化
(new App())->console->run();
