<?php

namespace Qklin\Kernel\Plus\Services;

use Exception;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Input\ArgvInput;

class KernelPlusService
{
    protected $app;
    protected $argvInput;

    private $router;
    private $annotate;

    // 是否全局注册过commands集合
    private $registeredCommandAll;

    // 已注册的调度的commands
    private $scheduleLoadCommands;

    /**
     * KernelPlusService constructor.
     * @param Application $app
     * @param ArgvInput   $input
     * @throws BindingResolutionException
     */
    public function __construct(Application $app, ArgvInput $input)
    {
        $this->app = $app;
        $this->argvInput = $input;
        $this->router = $this->app->make('qklin.kernel.plus.router');
        $this->annotate = $this->app->make('qklin.kernel.plus.annotate');
        $this->registeredCommandAll = false;
        $this->scheduleLoadCommands = [];
    }

    /**
     * 注册commands入口
     * @throws \ReflectionException
     */
    public function registerCommands(Kernel $kernel)
    {
        // 加载配置
        $this->app->configure('kernel_plus');
        $kernelPlusConfig = config('kernel_plus');

        // 自动注入command
        $commandSign = $this->argvInput->getFirstArgument();
        if (!$commandSign) {
            return;
        }

        // 加载所有的command，传入kernel 或 签名为: list
        if ($kernel instanceof Kernel && $commandSign == 'list') {
            $kernel->loadCommandFromDirs(array_map(function ($v) {
                return app()->basePath() . DIRECTORY_SEPARATOR . $v;
            }, $kernelPlusConfig['load_cmd_dirs']), $this);
            $this->registeredCommandAll = true;
            return;
        }

        if (!in_array($commandSign, $kernelPlusConfig['laravel_expect'])) {
            $this->command($commandSign);
        }
    }

    /**
     * 调度入口
     * 用于调度schedule:run
     * @param Schedule $schedule
     */
    public function schedule(Schedule $schedule)
    {
        $commandSign = $this->argvInput->getFirstArgument();
        if (!$commandSign) {
            return;
        }

        // 加载配置
        $this->app->configure('kernel_plus');
        $kernelPlusConfig = config('kernel_plus');

        if (!in_array($commandSign, $kernelPlusConfig['laravel_expect'])) {
            return;
        }

        $this->scheduleFromDirs($schedule, $kernelPlusConfig['load_cmd_dirs']);
    }

    /**
     * @param $commandSign
     * @throws \ReflectionException
     */
    public function command($commandSign)
    {
        $commandInfo = $this->router->parseCommands($commandSign);
        if (empty($commandInfo)) {
            return;
        }

        // 注册启动command
        $this->resolveCommand($commandInfo['class']);

        // 解决命令依赖
        if (!empty($commandInfo['depends'])) {
            foreach ($commandInfo['depends'] as $dependClass) {
                if ($dependClassSign = $this->getCommandSignFromClass($dependClass)) {
                    $this->command($dependClassSign);
                }
            }
        }
    }

    /**
     * @param $dependClass
     * @return bool|string
     * @throws \ReflectionException
     */
    private function getCommandSignFromClass($dependClass)
    {
        if (!class_exists($dependClass)) {
            throw new Exception("命令依赖类不存在:" . $dependClass);
        }
        $reflection = new \ReflectionClass($dependClass);
        if (!$reflection->hasConstant('COMMAND_SIGN')) {
            return '';
        }

        return $reflection->getConstant('COMMAND_SIGN');
    }

    /**
     * 注册command和加入调度器
     * @param Schedule $schedule
     * @param array    $pathsArr
     */
    public function scheduleFromDirs(Schedule $schedule, $pathsArr = [])
    {
        $pathsArr = array_unique(Arr::wrap($pathsArr));
        $pathsArr = array_filter($pathsArr, function ($file) {
            return $file;
        });
        if (empty($pathsArr)) {
            return;
        }

        $commandClasses = app('qklin.kernel.plus.cmds')->getCommandsClasses($pathsArr);
        foreach ($commandClasses as $cmdClass) {

            // 获取注解配置相关
            $commandHandleAnnotate = app('qklin.kernel.plus.cmds')->getCommandHandleAnnotate($cmdClass);
            if (empty($commandHandleAnnotate)) {
                continue;
            }

            // 注册command
            if (!$this->registeredCommandAll && !isset($this->scheduleLoadCommands[$cmdClass])) {
                $this->resolveCommand($cmdClass, $commandHandleAnnotate);
            }

            // 注册schedule
            $this->resolveSchedule($schedule, $commandHandleAnnotate);
        }
    }

    /**
     * @param $commandClass
     * @param $commandHandleAnnotate
     */
    public function resolveCommand($commandClass, $commandHandleAnnotate = [])
    {
        // 如果已经注册，直接返回
        if (isset($this->scheduleLoadCommands[$commandClass])) {
            return;
        }

        // 无，重新获取一次
        if (empty($commandHandleAnnotate)) {
            $commandHandleAnnotate = app('qklin.kernel.plus.cmds')->getCommandHandleAnnotate($commandClass);
        }

        // 如果还无，直接返回
        if (empty($commandHandleAnnotate)) {
            return;
        }

        // 无需注册，返回
        if ($commandHandleAnnotate['doc_params'][$commandHandleAnnotate['doc_vars']['command']] !== "true") {
            return;
        }

        Artisan::starting(function ($artisan) use ($commandClass) {
            $artisan->resolve($commandClass);
        });

        $this->scheduleLoadCommands[$commandClass] = $commandHandleAnnotate['cmd_sign'];
    }

    /**
     * @param Schedule $schedule
     * @param          $commandClass
     */
    public function resolveSchedule(Schedule $schedule, $commandHandleAnnotate)
    {
        $docVars = $commandHandleAnnotate['doc_vars'];
        $docParams = $commandHandleAnnotate['doc_params'];

        // 无需调度，返回
        if ($docParams[$docVars['schedule']] !== "true") {
            return;
        }

        // 参数有误，返回
        if (empty($docVars) || empty($docParams)) {
            return;
        }

        // 获取调度默认的参数
        $commandParamsStr = $docParams[$docVars['command_param']] ?? "";
        $commandParams = [];
        if ($commandParamsStr) {
            parse_str($commandParamsStr, $commandParams);
        }

        // 创建command
        $commandInstance = $schedule->command($commandHandleAnnotate['cmd_sign'], $commandParams);

        // 后台运行
        if ($docParams[$docVars['run_background']] === "true") {
            $commandInstance->runInBackground();
        }

        // 加锁
        if ($docParams[$docVars['overlapping']] === "true") {
            $commandInstance->withoutOverlapping();
        }

        // 运行时间
        if ($docParams[$docVars['run_time']]) {
            // 有参数的方法暂只支持cron: [cron|* * * * *]
            $runTimeActionParams = $docParams[$docVars['run_time']];
            $runTimeActionArr = explode("|", $runTimeActionParams);
            $runTimeAction = $runTimeActionArr[0] ?? "";
            $runTimeAction = trim($runTimeAction);
            $runTimeActionArgs = "";
            if ($runTimeAction === 'cron') {
                $runTimeActionArgs = trim($runTimeActionArr[1] ?? "");
            }

            // 执行
            if ($runTimeActionArgs) {
                $runTimeActionArgs = str_replace(["\\"], [""], $runTimeActionArgs);
                $commandInstance->{$runTimeAction}($runTimeActionArgs);
            } else {
                $commandInstance->{$runTimeAction}();
            }
        }

        // 记录日志
        if ($docParams[$docVars['log']]) {
            $logFile = $this->app->storagePath() . DIRECTORY_SEPARATOR . ($docParams[$docVars['log']] . "_" . date("Ymd") . '.log');
            $commandInstance->appendOutputTo($logFile);
        }
    }

    /**
     * @return mixed
     * @throws BindingResolutionException
     */
    public function getConsoleKernel()
    {
        return $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
    }
}