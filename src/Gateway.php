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

use think\App;
use think\console\Output;
use think\console\Input;
use Workerman\Worker;
use GatewayWorker\Gateway as GatewayWorker;

class Gateway
{
    /**
     * 配置参数
     * @var array
     */
	protected $options = [
        // Gateway进程名称, 方便status命令中查看统计
        'name' => 'think-socket-gateway',
        // Gateway进程监听协议, 支持http://、websocket://、text://
        'protocol' => 'text://',
        // Gateway进程监听的IP, 是暴露给客户端的让其连接的
        // 1、如果写0.0.0.0代表监听本机所有网卡，也就是内网、外网、本机都可以访问到
        // 2、如果是127.0.0.1，代表只能本机通过127.0.0.1访问，外网和内网都访问不到
        // 3、如果是内网ip例如:192.168.10.11，代表只能通过192.168.10.11访问，也就是只能内网访问，本机127.0.0.1也访问不了（如果监听的ip不属于本机则会报错）
        // 4、如果是外网ip例如110.110.110.110，代表只能通过外网ip 110.110.110.110访问，内网和本机127.0.0.1都访问不了(如果监听的ip不属于本机则会报错)
        'listen' => '0.0.0.0',
        // Gateway进程监听端口, 端口不能大于65535，请确认端口没有被其它程序占用，否则启动会报错。
        // 如果端口小于1024，需要root权限运行GatewayWorker才能有权限监听，否则报错没有权限。
        'port' => 8089,
        // Gateway进程数量, Gateway进程数不是开得越多越好，Gateway进程增多会导致进程间通讯开销变大。
        // 每个Gateway进程可以轻松处理5000连接的请求转发，业务同时在线连接数少于5000时可以只开1-2个Gateway进程。
        // 1万同时在线可以开2-3个Gateway进程，每5000个连接增加一个Gateway进程，依次类推。
        'count' => 1,
        // Gateway所在服务器的内网IP，默认填写127.0.0.1即可。
        // 多服务器分布式部署的时候需要填写真实的内网ip，不能填写127.0.0.1。
        // 注意：lanIp只能填写真实ip，不能填写域名或者其它字符串，无论如何都不能写0.0.0.0。
        'lan_ip' => '127.0.0.1',
        // Gateway内部通讯起始端口，Gateway进程启动后会监听一个本机端口，用来给BusinessWorker提供链接服务，
        // 然后Gateway与BusinessWorker之间就通过这个连接通讯。
        // 注意：这里设置的是Gateway监听本机端口的起始端口。
        // 比如启动了4个Gateway进程，startPort为4000，则每个Gateway进程分别启动的本地端口一般为4000、4001、4002、4003。
        // 当本机有多个Gateway/Business项目时，需要把每个项目的startPort设置成不同的段
        'start_port' => 4000,
        // 注册服务地址, 格式类似于 '127.0.0.1:1236'。
        // 如果是部署了多个register服务则格式是数组，类似['192.168.0.1:1236','192.168.0.2:1236']
        'register_address' => '127.0.0.1:1236',
        // Gateway通讯密钥
        'secret_key' => '',
        // 心跳检测时间间隔，单位：秒。如果设置为0代表不做任何心跳检测。
        'ping_interval' => 50,
        // 心跳检测频率，客户端连续$pingNotResponseLimit次$pingInterval时间内不发送任何数据(包括但不限于心跳数据)则断开链接，并触发onClose。
        // 如果设置为0代表客户端不用发送心跳数据，即通过TCP层面检测连接的连通性（极端情况至少10分钟才能检测到连接断开，甚至可能永远检测不到）
        'ping_not_response_limit' => 1,
        // 心跳数据，当需要服务端定时给客户端发送心跳数据时，$gateway->pingData设置为服务端要发送的心跳请求数据。
        // 心跳数据是任意的，只要客户端能识别即可，客户端收到心跳数据可以忽略不做任何处理。
        'ping_data' => 'ping',
        // 是否以守护进程启动
        'daemonize' => false,
        // 内容输出文件路径
        'stdout_file' => '',
        // pid文件路径
        'pid_file' => '',
        // 日志文件路径
        'log_file' => '',
	];

    /**
     * 架构函数
     * @access public
     * @param App $app 容器实例
     * @return void
     */
    public function __construct(App $app)
    {
        // 记录容器实例
        $this->app = $app;
        // 合并配置
		$this->options = array_merge($this->options, $this->app->config->get('socketgateway', []));
        // 初始化
		$this->init();
    }

    /**
     * 初始化
     * @access protected
	 * @return void
     */
	protected function init()
	{
        // 如果监听协议为空
        if(empty($this->options['protocol'])){
            // 抛出异常
            throw new \Exception('protocol can not be empty');
        }
        // 如果监听地址为空
        if(empty($this->options['listen'])){
            // 抛出异常
            throw new \Exception('listen can not be empty');
        }
        // 如果端口不合法
        if(!is_numeric($this->options['port']) || $this->options['port'] < 0 || $this->options['port'] > 65535){
            // 抛出异常
            throw new \Exception('port must be a number between 0 and 65535');
        }
	}

    /**
     * 启动
     * @access public
     * @param Input $input 输入
     * @param Output $output 输出
	 * @return void
     */
	public function start(Input $input, Output $output)
	{
        // 不是控制台模式
        if (!$this->app->runningInConsole()) {
            // 抛出异常
            throw new \Exception('only supports running in cli mode');
        }

        // 如果是守护进程模式
        if ($input->hasOption('daemon')) {
            // 修改配置为守护进程模式
            $this->options['daemonize'] = true;
        }

        // 进程名称为空
		if(empty($this->options['name'])){
            $this->options['name'] = 'think-socket-gateway';
        }
        // 构造新的运行时目录
		$runtimePath = $this->app->getRuntimePath() . $this->options['name'] . DIRECTORY_SEPARATOR;
        // 设置runtime路径
        $this->app->setRuntimePath($runtimePath);

        // 主进程reload
		Worker::$onMasterReload = function () {
			// 清理opcache
            if (function_exists('opcache_get_status')) {
                if ($status = opcache_get_status()) {
                    if (isset($status['scripts']) && $scripts = $status['scripts']) {
                        foreach (array_keys($scripts) as $file) {
                            opcache_invalidate($file, true);
                        }
                    }
                }
            }
        };

        // 内容输出文件路径
		if(!empty($this->options['stdout_file'])){
			// 目录不存在则自动创建
			$stdout_dir = dirname($this->options['stdout_file']);
			if (!is_dir($stdout_dir)){
				mkdir($stdout_dir, 0755, true);
			}
			// 指定stdout文件路径
			Worker::$stdoutFile = $this->options['stdout_file'];
		}
		// pid文件路径
		if(empty($this->options['pid_file'])){
			$this->options['pid_file'] = $runtimePath . 'worker' . DIRECTORY_SEPARATOR . $this->options['name'] . '.pid';
		}

		// 目录不存在则自动创建
		$pid_dir = dirname($this->options['pid_file']);
		if (!is_dir($pid_dir)){
			mkdir($pid_dir, 0755, true);
		}
		// 指定pid文件路径
		Worker::$pidFile = $this->options['pid_file'];

        // 日志文件路径
		if(empty($this->options['log_file'])){
			$this->options['log_file'] = $runtimePath . 'worker' . DIRECTORY_SEPARATOR . $this->options['name'] . '.log';
		}
		// 目录不存在则自动创建
		$log_dir = dirname($this->options['log_file']);
		if (!is_dir($log_dir)){
			mkdir($log_dir, 0755, true);
		}
		// 指定日志文件路径
		Worker::$logFile = $this->options['log_file'];

        // 如果指定以守护进程方式运行
        if (true === $this->options['daemonize']) {
            Worker::$daemonize = true;
        }

        // 实例化gateway进程
        $gateway = new GatewayWorker($this->options['protocol'] . $this->options['listen'] . ':' . $this->options['port']);
        // gateway名称，status方便查看
        $gateway->name = $this->options['name'];
        // gateway进程数
        $gateway->count = $this->options['count'];
        // 本机ip，分布式部署时使用内网ip
        $gateway->lanIp = $this->options['lan_ip'];
        // 内部通讯起始端口，假如$gateway->count=4，起始端口为4000
        // 则一般会使用4000 4001 4002 4003 4个端口作为内部通讯端口
        $gateway->startPort = $this->options['start_port'];
        // 服务注册地址
        $gateway->registerAddress = $this->options['register_address'];
        // Gateway通讯密钥
        $gateway->secretKey = $this->options['secret_key'];
        // 心跳间隔
        $gateway->pingInterval = $this->options['ping_interval'];
        // 心跳间隔
        $gateway->pingNotResponseLimit = $this->options['ping_not_response_limit'];
        // 心跳数据
        $gateway->pingData = $this->options['ping_data'];
        
        // 启动
		Worker::runAll();
	}

    /**
     * 停止
     * @access public
     * @return void
     */
    public function stop()
    {
        Worker::stopAll();
    }
}
