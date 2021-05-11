<?php
namespace PMVC\PlugIn\debug;

use PMVC as p;
use PMVC\Event;

p\l(__DIR__ . '/src/DebugDumpInterface.php');

if (defined(__NAMESPACE__ . '\INPUT_FIELD')) {
    return;
}

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__ . '\debug';
p\initPlugIn(['utf8' => null]);

const INPUT_FIELD = '--trace';
const DEFAULT_ERROR_HTTP_CODE = 500;
const TRACE = 'trace';
const DEBUG = 'debug';
const INFO = 'info';
const WARN = 'warn';
const ERROR = 'error';

/**
 * @parameters string  output    Debug output function [debug_console|debug_store|debug_cli]
 * @parameters string  truncate  Debug truncate dump function parameter string lengths
 * @parameters numeric level     Debug dump level
 * @parameters numeric traceFrom option for split debug_backtrace
 */
class debug extends p\PlugIn
{
    private $run = false;
    private $_output;
    private $_level = null;
    private $_dumpLevel;
    private $_utf8;

    public function init()
    {
        if (empty($this['truncate'])) {
            $this['truncate'] = 100;
        }
        if (!strlen($this['traceFrom'])) {
            $this['traceFrom'] = 6;
        }
        p\callPlugin('dispatcher', 'attach', [$this, Event\MAP_REQUEST]);
        if (isset($_REQUEST[INPUT_FIELD])) {
            $this->setLevel($_REQUEST[INPUT_FIELD], false);
        } elseif (isset($this['level'])) {
            $this->setLevel($this['level'], false);
        }
        $this->_utf8 = \PMVC\plug('utf8');
    }

    public function onMapRequest($subject)
    {
        $subject->detach($this);
        $request = p\callPlugin('controller', 'getRequest');
        $trace = p\get($request, INPUT_FIELD);
        if (!empty($trace)) {
            $this->setLevel($trace, false);
        }
    }

    public function isShow($runLevel, $showLevel, $default = 1)
    {
        /**
         * if user input multi level, will use first standard level.
         * such as &--trace=debug,curl will use debug one.
         */
        return $this->LevelToInt(
            $runLevel,
            $this->LevelToInt($showLevel, $default)
        ) >= $this->LevelToInt($showLevel, $default);
    }

    /**
     * @params string $inputLevel The transfer one
     * @params int $default If input not found, return this one
     *
     * @return int
     */
    public function LevelToInt($inputLevel, $default = 1)
    {
        $levels = [
            TRACE => 1,
            DEBUG => 2,
            INFO => 3,
            WARN => 4,
            ERROR => 5,
        ];
        $inputLevels = $this->getLevels($inputLevel);
        $arrLevel = [];
        foreach ($inputLevels as $lev) {
            if (isset($levels[$lev])) {
                $arrLevel[] = $levels[$lev];
            }
        }
        if (count($arrLevel)) {
            $result = min($arrLevel);
        } else {
            $result = $default;
        }

        return $result;
    }

    public function getLevels($level = null)
    {
        if (is_null($level)) {
            $level = $this->_level;
        }
        return $level ? array_map('trim', explode(',', $level)) : [];
    }

    public function setLevel($level, $force = true)
    {
        if (!isset($this->_level) || $force) {
            $this->_level = $level;
        }
        p\callPlugin('dispatcher', 'notify', ['resetDebugLevel']);
    }

    public function setOutput()
    {
        $output = p\get($this, 'output', 'debug_console');
        if (!is_object($output)) {
            $outputParam = [];
            if (isset($this->_level)) {
                $outputParam['level'] = $this->_level;
            }
            $output = p\plug($output, $outputParam);
        }
        if (empty($output)) {
            return !trigger_error(
                '[PMVC:PlugIn:Debug:getOutput] Get Output failded.',
                E_USER_WARNING
            );
        }
        if (!$output->is(__NAMESPACE__ . '\DebugDumpInterface')) {
            return !trigger_error(
                '[' .
                    get_class($output) .
                    '] is not a valid debug output object,' .
                    'expedted DebugDumpInterface. ' .
                    print_r($output, true),
                E_USER_WARNING
            );
        }
        $this->_output = $output;
    }

    public function getOutput()
    {
        if (!$this->_output) {
            $this->setOutput();
        }
        if (p\getOption(_VIEW_ENGINE) === 'json') {
            $this['output'] = 'debug_store';
            $this->setOutput();
        }
        return $this->_output;
    }

    public function d()
    {
        $a = func_get_args();
        $a0 = $a[0];
        if ($this->isException($a0) || (1 === count($a) && is_string($a0))) {
            $tmp = $a0;
        } else {
            $tmp = array_map(function ($o) {
                return print_r($o, true);
            }, $a);
        }
        if (!$this->run) {
            $this->run = true;
            $this->dump($tmp);
            $this->run = false;
        }
        return $tmp;
    }

    public function parseTrace($raw, $sliceFrom = 0, $length = null)
    {
        if ($sliceFrom || $length) {
            $raw = array_slice($raw, $sliceFrom, $length);
        }
        $arr = [];
        $i = 1;
        $this->_dumpLevel = null;
        $keepArgs = false;
        \PMVC\dev(function () use (&$keepArgs) {
            $keepArgs = true;
        }, 'debug-keep-args');
        foreach ($raw as $k => $v) {
            $args = !empty($v['args']) ? $this->parseArgus($v['args']) : '';
            $name = $v['function'];
            $file = '[] ';
            if (isset($v['file'])) {
                $file = '[' . basename($v['file']) . '] ';
            }
            if ('handleError' === $name) {
                if (E_USER_WARNING === \PMVC\value($v, ['args', 0])) {
                    $this->_dumpLevel = WARN;
                } else {
                    $this->_dumpLevel = ERROR;
                }
            }
            if (!empty($v['object'])) {
                $name = get_class($v['object']) . $v['type'] . $name;
                unset($v['object']);
            }
            if (!$keepArgs) {
                unset($v['args']);
            }
            unset($v['type']);
            $arr[$i . ': ' . $file . $name . '(' . $args . ')'] = $v;
            $i++;
        }
        $raw = null;
        unset($raw, $k, $v);
        return $arr;
    }

    public function httpResponseCode($bool = true)
    {
        if ($bool && !headers_sent() && p\exists('http', 'plugin')) {
            http_response_code(
                p\getOption('httpResponseCode', DEFAULT_ERROR_HTTP_CODE)
            );
            p\callPlugin('cache_header', 'noCache');
        }
    }

    public function dump($content)
    {
        $console = $this->getOutput();
        if (!$console) {
            return;
        }
        $traceLength = $this['traceLength'] ? $this['traceLength'] : null;
        if ($this->isException($content)) {
            $message = $content->getMessage();
            $trace = $this->parseTrace($content->getTrace(), 0, $traceLength);
            $errorLevel = ERROR;
        } else {
            $message = &$content;
            $trace = $this->parseTrace(
                debug_backtrace(),
                $this['traceFrom'],
                $traceLength
            );
            $errorLevel = $this->_dumpLevel;
            if (is_null($errorLevel)) {
                $errorLevel = DEBUG;
            }
        }
        $this->httpResponseCode(!in_array($errorLevel, [WARN, TRACE]));
        $json = p\fromJson($message, true);
        if (!is_array($json)) {
            $json = $console->escape($message);
        }
        $console->dump($json, $errorLevel);
        unset($content, $message, $json);
        $console->dump($trace, 'trace');
        unset($trace, $console);
    }

    /**
     * Check is exception
     *
     * @param object $object
     */
    public function isException($object)
    {
        if (is_a($object, 'Exception') || is_a($object, 'Error')) {
            return true;
        } else {
            return false;
        }
    }

    public function objToStr($o)
    {
        if (is_object($o)) {
            $o = 'class ' . get_class($o);
        }
        if (empty($o)) {
            if (is_array($o)) {
                return '[]';
            } else {
                return 'NULL';
            }
        }
        return trim(print_r($o, true));
    }

    public function parseArgus($a)
    {
        if (!is_array($a)) {
            return $a;
        }
        $b = [];
        $aLen = count($a);
        $console = $this->getOutput();
        for ($i = 0; $i < $aLen; $i++) {
            if (is_object($a[$i])) {
                $param = 'class ' . get_class($a[$i]);
            } elseif (is_array($a[$i])) {
                $clone = array_merge([], $a[$i]);
                $param = key($clone) . ' => ' . $this->objToStr(reset($clone));
                $param = 'array ' . $param;
            } elseif (is_null($a[$i])) {
                $param = 'NULL';
            } else {
                $param = is_numeric($a[$i]) ? $a[$i] : (string) $a[$i];
                if (!strlen($param)) {
                    $param = "''";
                }
            }
            if (is_numeric($param)) {
                $b[] = $param;
            } else {
                $param = strip_tags($param); // better memory usage.
                $b[] = $console->escape(
                    $this->_utf8->substr($param, 0, $this['truncate'])
                );
            }
        }
        return join(', ', $b);
    }
}
