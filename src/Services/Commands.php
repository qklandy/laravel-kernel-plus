<?php

namespace Qklin\Kernel\Plus\Services;

use App\Console\BaseCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class Commands
{
    public function __construct()
    {
    }

    /**
     * 获取command所有类
     * @param $pathDirar
     * @return array
     * @throws \ReflectionException
     */
    public function getCommandsClasses($pathDirar)
    {
        $classFiles = [];
        foreach ($pathDirar as $path) {
            if (is_file($path)) {
                $this->pushCmdFile($classFiles, $path);
            } else {
                foreach ($this->scanDir($path) as $cmdFile) {
                    $this->pushCmdFile($classFiles, $cmdFile);
                }
            }
        }

        return $classFiles;
    }

    /**
     * 遍历目录获取Commands目录下的数据
     * @param      $path
     * @param bool $isSubCommandsDir
     * @return array
     */
    private function scanDir($path, $isSubCommandsDir = false)
    {
        $files = [];

        // 文件
        if (is_file($path)) {
            array_push($files, $path);
            return $files;
        }

        $commandDir = substr($path, -strlen('Commands')) == 'Commands' ? true : false;

        $isSubCommandsDir = $commandDir ? true : $commandDir;

        // 目录遍历 并迭代
        foreach (glob($path . "/*") as $subPath) {
            $files = array_merge($files, $this->scanDir($subPath, $isSubCommandsDir));
        }

        return array_unique($files);
    }

    /**
     * @param $classFiles
     * @param $commandFile
     * @throws \ReflectionException
     */
    private function pushCmdFile(&$classFiles, $commandFile)
    {
        $commandClass = str_replace(
            [app()->basePath(), '/', '.php'],
            ['', '\\', ''],
            $commandFile
        );

        $cmdReflection = (new \ReflectionClass($commandClass));
        if (!$cmdReflection->isAbstract()
            && $cmdReflection->isSubclassOf(Command::class)) {
            array_push($classFiles, $commandClass);
        }
    }

    /**
     * @param $commandClass
     * @return bool
     * @throws \ReflectionException
     */
    public function getCommandHandleAnnotate($commandClass)
    {
        $commandHandleAnnotate = [];
        $reflection = new \ReflectionClass($commandClass);
        if (!$reflection->hasMethod('handle')) {
            return $commandHandleAnnotate;
        }

        $refMethod = $reflection->getMethod('handle');

        // 不是pubic, 直接跳过
        if (!$refMethod->isPublic()) {
            return $commandHandleAnnotate;
        }

        // 获取注释参数
        $docComment = $refMethod->getDocComment();

        // 无注解直接跳过
        if (!$docComment) {
            return $commandHandleAnnotate;
        }

        $annotateService = app('qklin.kernel.plus.annotate');
        $docParams = $annotateService->parseLines($docComment)->parseSimple();

        // 是否开启command
        $command = $annotateService->getDocVar('command');
        $deprecated = $annotateService->getDocVar('deprecated');

        // 开启command，且注解废弃:deprecated
        if ((isset($docParams[$command]) && $docParams[$command] != "true")
            || isset($docParams[$deprecated])) {
            return false;
        }

        $commandHandleAnnotate['doc_params'] = $docParams;
        $commandHandleAnnotate['doc_vars'] = $annotateService->getDocVar();

        // 获取依赖属性
        $commandHandleAnnotate['depends'] = [];
        if ($reflection->hasConstant('DEPENDS')) {
            $commandHandleAnnotate['depends'] = $reflection->getConstant('DEPENDS');
        }

        $commandHandleAnnotate['cmd_sign'] = '';
        if ($reflection->hasConstant('COMMAND_SIGN')) {
            $commandHandleAnnotate['cmd_sign'] = $reflection->getConstant('COMMAND_SIGN');
        }

        return $commandHandleAnnotate;
    }
}
