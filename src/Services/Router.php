<?php

namespace Qklin\Kernel\Plus\Services;

use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;

class Router
{
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 注册command
     * @param $commandSign
     * @return array|mixed
     * @throws \ReflectionException
     */
    public function parseCommands($commandSign)
    {
        if (!$commandSign) {
            return [];
        }

        $commandInfo = $this->parseCommandFromSign($commandSign);
        if (!in_array($commandInfo['prefix'], $this->getPrefixs())) {
            return [];
        }

        return $this->resolveCommand($commandInfo);
    }

    /**
     * @param $commandInfo
     * @return mixed
     * @throws Exception
     */
    public function resolveCommand($commandInfo)
    {
        // todo 判断已经注册过，就跳过

        // 找不到对应的类，抛出错误
        if (!class_exists($commandInfo['class'])) {

            $errMsg = "无法匹配脚本命令规则模式，找不到类: "
                . "[{$commandInfo['class']}]；json: " . qklin_json_encode($commandInfo);
            if (env('APP_ENV') == 'PROD') {
                $errMsg = "无法匹配脚本命令规则模式";
            }
            throw new Exception($errMsg, -110);
        }

        $this->injectCommandAnnotate($commandInfo);

        // 判断是否允许访问的控制器方法
        if (!isset($commandInfo['doc_params'])
            || !isset($commandInfo['doc_params']['command'])
            || $commandInfo['doc_params']['command'] != "true") {

            $errMsg = "该脚本未被允许访问: [{$commandInfo['class']}::{$commandInfo['command']}]";
            if (env('APP_ENV') == 'PROD') {
                $errMsg = "该脚本未被允许访问: [{$commandInfo['command']}]";
            }
            throw new Exception($errMsg, -111);
        }

        // 中断
        $this->interrupt($commandInfo);

        // todo 注入中间件

        // 注册命令command
        return $commandInfo;

    }

    /**
     * 特殊的中断异常
     * @param $commandInfo
     * @return bool
     */
    protected function interrupt($commandInfo)
    {
        return true;
    }

    /**
     * 反射处理
     * @param $commandInfo
     * @return void
     */
    protected function injectCommandAnnotate(&$commandInfo)
    {
        $commandHandleAnnotate = app('qklin.kernel.plus.cmds')->getCommandHandleAnnotate($commandInfo['class']);
        if (!empty($commandHandleAnnotate)) {
            $commandInfo = array_merge($commandInfo, $commandHandleAnnotate);
        }
    }

    /**
     * 解析command
     * @param $commandSign
     * @return array
     */
    public function parseCommandFromSign($commandSign)
    {
        $cmdParamsArr = explode(":", $commandSign);
        $cmdParamsArr = array_map(function ($v) {

            if ($v == env('KERNEL_PLUS_COMMANDS_DIR', 'cmd')) {
                $v = 'commands';
            }

            // [.]转换成[_]
            $tmp = ucfirst(str_replace(".", "_", $v));

            // 兼容除方法外的驼峰目录: [m/h/inside]/live-video/live/do-it -> LiveVideo/Live/doIt
            $tmp = preg_replace_callback("/(\-[^-]+)/", function ($matches) {
                return ucfirst(substr($matches[0], 1));
            }, $tmp);

            return $tmp;

        }, $cmdParamsArr);

        // 移除开头
        $prefix = strtolower(array_splice($cmdParamsArr, 0, 1)[0]);

        // 获取控制器类
        // app/http目录下
        $commandClass = "";
        if ($prefix == env('KERNEL_PLUS_ORIGIN_PREFIX', 'c')) {
            $commandClass = "\\App\\Console\\" . implode("\\", $cmdParamsArr);
        }
        // 自定义的结构模块目录下，默认App\Biz
        if (in_array($prefix, explode(",", env('KERNEL_PLUS_MODULE_PREFIX', 'cm')))) {
            $commandClass = "\\App\\" . env('KERNEL_PLUS_MODULE_DIR', 'Biz') . "\\" . implode("\\", $cmdParamsArr);
        }

        return [
            'command' => $commandSign,
            'prefix'  => $prefix, //前缀
            'class'   => $commandClass, //实际控制器类
        ];
    }

    /**
     * @return array
     */
    public function getPrefixs()
    {
        // 只处理新路由前缀 可兼容原本的路由配置
        $prefixs = explode(
            ",",
            env('KERNEL_PLUS_ORIGIN_PREFIX', 'c') . "," . env('KERNEL_PLUS_MODULE_PREFIX', 'cm')
        );
        return $prefixs;
    }
}
