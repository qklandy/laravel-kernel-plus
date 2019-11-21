# Laravel/Lumen kernel 增强库
![PHP VERSION](https://img.shields.io/badge/php-^7.0-blue)
![MIT](https://img.shields.io/github/license/qklandy/laravel-kernel-plus) 

## Table of Contents

1. [安装](#安装)
1. [使用](#使用)
    1. [说明](#说明)

## 安装

### Composer:
```
composer require qklin/laravel-kernel-plus
```

### config/app.php
```
'providers' => [
    ...
    Qklin\Kernel\Plus\KernelPlusProvider::class,
]
```

### kernel.php
app/Console/Kernel.php
```
<?php

namespace App\Console;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Qklin\Kernel\Plus\Services\KernelPlusService;
use Symfony\Component\Finder\Finder;

class Kernel extends ConsoleKernel
{
    /**
     * 配置注册command
     * @var array
     */
    protected $commands = [

    ];

    /**
     * 调度
     * @param Schedule $schedule
     */
    protected function schedule(Schedule $schedule)
    {
        // 加载调度
        app("qklin.kernel.plus.service")->schedule($schedule);
    }

    /**
     * Artisan 直接注册command
     */
    protected function commands()
    {
        // 自动注册command 或 加载全部
        app("qklin.kernel.plus.service")->registerCommands($this);

        require base_path('routes/console.php');
    }

    /**
     * 加载支持跨多级目录下commands脚本
     * @param                   $paths
     * @param KernelPlusService $kernelPlus
     */
    public function loadCommandFromDirs($paths, KernelPlusService $kernelPlus)
    {
        $paths = array_unique(Arr::wrap($paths));

        $paths = array_filter($paths, function ($path) {
            return is_dir($path);
        });

        if (empty($paths)) {
            return;
        }

        $namespace = $this->app->getNamespace();

        foreach ((new Finder)->in($paths)->files() as $command) {
            $command = "\\" . $namespace . str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    Str::after($command->getPathname(), realpath(app_path()) . DIRECTORY_SEPARATOR)
                );

            $kernelPlus->resolveCommand($command);
        }
    }
}
```

## 使用

### 说明
```
# 会加载注册所有的comand命令脚本，并自动加入schedule队列， 无需手动个添加
php artisan schedule:run

# 自动注册command，并执行
php artisan c:cmd:core:test
```

### 注解说明
command::handle()
```
// * @command true                         //注册command
// * @commandParams param1=foo&--option=1  //本参数可省略
// * @schedule true                        //加入schedule 
// * @runTime everyMinute                  //无参数的所有方法都支持
// * @runTime cron|* * * * *               // 目前只支持cron带参数，方法和参数[|]分隔
// * @withoutOverlapping true
// * @runInBackground true
// * @appendOutputTo test/log              //记录日志，位置：storage目录
// * @deprecated
```

### env
默认的env配置
```
KERNEL_PLUS_ORIGIN_PREFIX=c
KERNEL_PLUS_MODULE_PREFIX=cm
KERNEL_PLUS_MODULE_DIR=Biz
KERNEL_PLUS_COMMANDS_DIR=cmd
KERNEL_DOCMENT_CMD=command
KERNEL_DOCMENT_CMD_PARAM=commandParams
KERNEL_DOCMENT_SCHEDULE=schedule
KERNEL_DOCMENT_RUN_TIME=runTime
KERNEL_DOCMENT_RUN)BACKGROUND=runInBackground
KERNEL_DOCMENT_LOG=appendOutputTo
KERNEL_DOCMENT_OVER_LAPPING=withoutOverlapping
KERNEL_DOCMENT_DEPRACATED=appendOutputTo
```

### demo
```
<?php

namespace App\Console\Commands\Core;

use App\Console\BaseCommand;

class Test extends BaseCommand
{
    // 依赖关系
    // 自动解决依赖，适用于单脚本依赖，不适用http
    const DEPENDS = [
        Testdepend::class
    ];

    // 脚本命令注册名
    const COMMAND_SIGN = 'c:cmd:core:test';

    protected $signature = self::COMMAND_SIGN . ' {param1?}';
    protected $description = '自动注入脚本测试';

    /**
     * @command            true
     * @schedule           true
     * @runTime            everyMinute
     * @runTime            cron|* 1 * * *
     * @withoutOverlapping true
     * @runInBackground    true
     * @appendOutputTo     logs/c_core_test 
     */
    public function handle()
    {
        $this->info("test finish");
    }
}
```


