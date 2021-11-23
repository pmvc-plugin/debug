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
    private $_bInitTrace;
    private $_keepArgs;
    private $_debugAll;
    private $_errorException;

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
        p\callPlugin('dispatcher', 'notify', [Event\WILL_SET_VIEW]);
        if (p\getOption(_VIEW_ENGINE) === 'json') {
            $this['output'] = 'debug_store';
            $this->setOutput();
        } elseif (!$this->_output) {
            $this->setOutput();
        }
        return $this->_output;
    }

    public function d()
    {
        $a = func_get_args();
        $a0 = $a[0];
        if (
            $this->isException($a0) ||
            (1 === count($a) && \PMVC\testString($a0))
        ) {
            $tmp = $a0;
        } else {
            $tmp = print_r($a, true);
        }
        if (!$this->run) {
            $this->run = true;
            $this->dump($tmp);
            $this->run = false;
        }
        return $tmp;
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
            $errorLevel = $this->_dumpLevel;
            if (is_null($errorLevel)) {
                $errorLevel = WARN;
            }
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
        $console->dump($trace, TRACE);
        unset($trace, $console);
        $this->_dumpLevel = null;
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

    /**
     * Check is exception
     *
     * @param object $object
     */
    public function isException($object)
    {
        if (is_a($object, 'Error')) {
            $this->_dumpLevel = ERROR;
            $this->_errorException = $object;
            return true;
        }
        if (is_a($object, 'Exception')) {
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

    private function _initTrace()
    {
        if (!$this->_bInitTrace) {
            \PMVC\dev(
                /**
                 * @help let trace information not demise args
                 */
                function () {
                    $this->_bInitTrace = true;
                    $this->_keepArgs = true;
                    return 'Set keepArgs to true';
                },
                'debug-keep-args'
            );
            \PMVC\dev(
                /**
                 * @help let trace information not demise args
                 */
                function () {
                    $this->_bInitTrace = true;
                    $this->_debugAll = true;
                    return 'Set debugAll to true';
                },
                'debug-all'
            );
        }
    }

    public function parseTrace($raw, $sliceFrom = 0, $length = null)
    {
        if ($sliceFrom || $length) {
            $raw = array_slice($raw, $sliceFrom, $length);
        }
        $arr = [];
        $i = 1;
        $this->_initTrace();
        $console = $this->getOutput();
        $debugAll = $this->_debugAll;
        $keepArgs = $this->_keepArgs;
        if (isset($this->_errorException)) {
            $errArgs = $this->_errorException;
            $arr[] = [
                'error' => [
                    'no' => $errArgs->getCode(),
                    'message' => $errArgs->getMessage(),
                    'file' => $errArgs->getFile(),
                    'line' => $errArgs->getLine(),
                ],
            ];
            $this->_errorException = null;
        }
        foreach ($raw as $k => $v) {
            if (isset($v['file'])) {
                $valFile = $v['file'];
                if (!$debugAll) {
                    if (false !== strrpos($valFile, '/pmvc/src/Alias.php')) {
                        continue;
                    }
                    if (false !== strrpos($valFile, '/pmvc/src/Adapter.php')) {
                        continue;
                    }
                }
                $file = '[' . basename($valFile) . '] ';
            } else {
                $file = '[] ';
            }
            $name = $this->_parseName($v);
            if (!$name) {
                continue;
            }
            if (0 === strpos($v['function'], 'call_user_func')) {
                $v['call'] = $this->_parseCall($v);
                if (empty($v['call'])) {
                    continue;
                }
            }

            $args = !empty($v['args'])
                ? $this->_parseArgus($v['args'], $console)
                : '';
            $mark = null;
            if ('handleError' === $v['function']) {
                if (E_USER_WARNING === \PMVC\value($v, ['args', 0])) {
                    $this->_dumpLevel = WARN;
                } else {
                    $this->_dumpLevel = ERROR;
                }
                $mark = '* ';
                $errArgs = $v['args'];
                $v['error'] = [
                    'no' => $errArgs[0],
                    'message' => $errArgs[1],
                    'file' => $errArgs[2],
                    'line' => $errArgs[3],
                ];
                unset($v['args']);
            } else {
                if (!$keepArgs) {
                    unset($v['args']);
                }
            }

            unset($v['type']);
            $arr[$i . ': ' . $mark . $file . $name . '(' . $args . ')'] = $v;
            $i++;
        }
        $raw = null;
        unset($raw, $k, $v);
        return $arr;
    }

    private function _parseCall($v)
    {
        $args0 = \PMVC\value($v, ['args', 0]);
        $class = \PMVC\isArray($args0) ? \PMVC\get($args0, 0) : null;
        if ($class) {
            $className = get_class($class);
            if ('PMVC\Adapter' === $className) {
                return false;
            }
            $call = [$className, $args0[1]];
        } else {
            $call = $args0;
        }
        return $call;
    }

    private function _parseName(&$v)
    {
        $name = $v['function'];
        if (!empty($v['object'])) {
            $className = get_class($v['object']);
            if ('PMVC\Adapter' === $className) {
                return false;
            }
            $name = $className . $v['type'] . $name;
            unset($v['object']);
        }
        return $name;
    }

    private function _parseArgus($a, $console)
    {
        if (!is_array($a)) {
            return $a;
        }
        $b = [];
        $aLen = count($a);
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
                    $this->_utf8->substr($param, 0, $this['truncate']),
                    TRACE
                );
            }
        }
        return join(', ', $b);
    }
}
