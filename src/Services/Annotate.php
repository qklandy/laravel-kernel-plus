<?php

namespace Qklin\Kernel\Plus\Services;

class Annotate
{
    private $_lines;

    private $_docVars;

    public function __construct()
    {
        // command::handle()
        // * @command true
        // * @schedule true
        // * @runTime everyMinute Or cron|* * * * *
        // * @withoutOverlapping true
        // * @runInBackground true
        // * @appendOutputTo test/log
        // * @deprecated
        $this->_docVars = [
            'command'        => env('KERNEL_DOCMENT_CMD', 'command'),
            'command_param'  => env('KERNEL_DOCMENT_CMD_PARAM', 'commandParams'),
            'schedule'       => env('KERNEL_DOCMENT_SCHEDULE', 'schedule'),
            'run_time'       => env('KERNEL_DOCMENT_RUN_TIME', 'runTime'),
            'run_background' => env('KERNEL_DOCMENT_RUN)BACKGROUND', 'runInBackground'),
            'log'            => env('KERNEL_DOCMENT_LOG', 'appendOutputTo'),
            'overlapping'    => env('KERNEL_DOCMENT_OVER_LAPPING', 'withoutOverlapping'),
            'deprecated'     => env('KERNEL_DOCMENT_DEPRACATED', 'deprecated'),
        ];
    }

    /**
     * @return array
     */
    public function getDocVar($key = null)
    {
        if (is_null($key)) {
            return $this->_docVars;
        }

        return $this->_docVars[$key] ?? "";
    }

    /**
     * 设置docvar
     * @param $key
     * @param $val
     */
    public function setDocVar($key, $val)
    {
        $this->_docVars[$key] = $val;
    }

    /**
     * 解析注解
     * @return array
     */
    public function parseSimple()
    {
        $params = [];
        if (empty($this->_lines)) {
            return $params;
        }

        foreach ($this->_lines as $line) {
            $line = trim($line);
            if (strpos($line, '@') === 0) {
                if (strpos($line, ' ') > 0) {
                    // 获取参数名和值
                    $param = substr($line, 1, strpos($line, ' ') - 1);
                    $value = trim(substr($line, strlen($param) + 2)); // Get the value
                } else {
                    $param = substr($line, 1);
                    $value = '';
                }

                $params[$param] = $value;
            } else {
                if (isset($params["doc_title"])) {
                    $params["doc_title"] .= PHP_EOL . $line;
                } else {
                    $params["doc_title"] = $line;
                }
            }
        }

        return $params;
    }

    /**
     * 解析行
     * @param $doc
     * @return Annotate
     */
    public function parseLines($doc)
    {
        $this->_lines = [];
        if (preg_match('#^/\*\*(.*)\*/#s', $doc, $comment) === false) {
            return $this;
        }

        $comment = trim($comment[1] ?? "");
        if (!$comment) {
            return $this;
        }

        if (preg_match_all('#^\s*\*(.*)#m', $comment, $lines) === false) {
            return $this;
        }

        $this->_lines = $lines[1] ?? [];

        return $this;
    }
}
