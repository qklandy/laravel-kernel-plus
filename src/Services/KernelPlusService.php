<?php

namespace Qklin\Kernel\Plus\Services;

use Exception;
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
    }

    /**
     * 启动
     * @param Schedule $schedule
     * @throws BindingResolutionException
     * @throws \ReflectionException
     */
    public function handle(Schedule $schedule)
    {
        $commandSign = $this->argvInput->getFirstArgument();
        if (!$commandSign) {
            return;
        }

        // 加载配置
        $this->app->configure('kernel_plus');
        $kernelPlusConfig = config('kernel_plus');

        if (in_array($commandSign, $kernelPlusConfig['laravel_expect'])) {
            $this->shecdule($schedule, $kernelPlusConfig['load_cmd_dirs']);
            return;
        }

        $this->command($commandSign);
    }

    /**
     * @param $commandSign
     * @return array
     * @throws BindingResolutionException
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
     * @throws BindingResolutionException
     */
    public function shecdule(Schedule $schedule, $pathsArr = [])
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
            // 注册command
            $this->resolveCommand($cmdClass);
            // 注册schedule
            $this->resolveSchedule($schedule, $cmdClass);
        }
    }

    /**
     * @param $commandClass
     */
    public function resolveCommand($commandClass)
    {
        Artisan::starting(function ($artisan) use ($commandClass) {
            $artisan->resolve($commandClass);
        });
    }

    /**
     * @param Schedule $schedule
     * @param          $commandClass
     */
    public function resolveSchedule(Schedule $schedule, $commandClass)
    {
        $commandHandleAnnotate = app('qklin.kernel.plus.cmds')->getCommandHandleAnnotate($commandClass);
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
            $runTimeAction = explode("|", $runTimeActionParams);
            $runTimeAction = $runTimeAction[0] ?? "";
            $runTimeAction = trim($runTimeAction);
            $runTimeActionArgs = "";
            if ($runTimeAction === 'cron') {
                $runTimeActionArgs = trim($runTimeAction[1] ?? "");
            }
            if ($runTimeActionArgs) {
                $commandInstance->{$runTimeAction}($runTimeActionArgs);
            } else {
                $runTimeActionArgs->{$runTimeAction}();
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
