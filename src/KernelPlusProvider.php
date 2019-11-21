<?php

namespace Qklin\Kernel\Plus;

use Qklin\Kernel\Plus\Services\Annotate;
use Qklin\Kernel\Plus\Services\Commands;
use Qklin\Kernel\Plus\Services\KernelPlusService;
use Qklin\Kernel\Plus\Services\Router;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\ArgvInput;

class KernelPlusProvider extends ServiceProvider
{
    /**
     * 注册
     */
    public function register()
    {
        $this->app->singleton("qklin.kernel.plus.service", function ($app) {
            return new KernelPlusService($app, new ArgvInput());
        });


        // 注册路由辅助工具
        if (!$this->app->bound('qklin.kernel.plus.router')) {
            $this->app->singleton('qklin.kernel.plus.router', function ($app) {
                return new Router($app);
            });
        }
        if (!$this->app->bound('qklin.kernel.plus.annotate')) {
            $this->app->singleton('qklin.kernel.plus.annotate', Annotate::class);
        }
        if (!$this->app->bound('qklin.kernel.plus.cmds')) {
            $this->app->singleton('qklin.kernel.plus.cmds', Commands::class);
        }
    }

    /**
     * 启动加载器
     */
    public function boot()
    {
        // 加入到kernel.php的schedule()里
//        $this->app->make("qklin.kernel.plus.service")->handle();
    }
}
