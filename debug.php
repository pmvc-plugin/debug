<?php
namespace PMVC\PlugIn\debug;

use PMVC as p;
use PMVC\Event;

\PMVC\l(__DIR__.'/src/DebugDumpInterface.php');

if (defined(__NAMESPACE__.'\INPUT_FIELD')) {
   return; 
}

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\debug';
const INPUT_FIELD = '--trace';

/**
 * @parameters string  output   Debug output function [debug_console|debug_store|debug_cli]
 * @parameters string  truncate Debug truncate dump function parameter string lengths 
 * @parameters numeric level    Debug dump level 
 */
class debug extends p\PlugIn
{
    private $run=false;
    private $_output;
    private $_detectTraceErrorLevel;

    public function init()
    {
        if (empty($this['truncate'])) {
            $this['truncate'] = 100;
        }
        \PMVC\callPlugin(
            'dispatcher',
            'attach',
            [ 
                $this,
                Event\MAP_REQUEST,
            ]
        );
        $this->setLevel(p\get($_REQUEST,INPUT_FIELD), false);
    }

    public function onMapRequest($subject)
    {
        $subject->detach($this);
        $request = p\plug('controller')->getRequest();
        $trace = p\get(
            $request,
            INPUT_FIELD
        );
        $this->setLevel($trace, false);
    }

    public function isShow($runLevel, $showLevel, $default=1)
    {
        /**
        * if user input multi level, will use first found standard level.
        * such as &--trace=debug,curl will use debug one.
        */
        return 
            $this->LevelToInt(
                $runLevel,
                $this->LevelToInt($showLevel, $default)
            ) >=
            $this->LevelToInt(
                $showLevel,
                $default
            );
    }

    /**
     * @params string $inputLevel The transfer one
     * @params int $default If input not found, return this one
     *
     * @return int
     */
    public function LevelToInt($inputLevel, $default=1)
    {
        $levels =  [
            'trace'=>1,
            'debug'=>2,
            'info'=>3,
            'warn'=>4,
            'error'=>5
        ];
        $inputLevels = explode(',', $inputLevel); 
        foreach ($inputLevels as $lev) {
            if (isset($levels[$lev])) {
                return $levels[$lev];
            }
        }

        return $default;
    }

    public function getLevels()
    {
        return explode(',', $this['level']);
    }

    public function setLevel($level, $force=true)
    {
       if (!isset($this['level']) || $force) {
            $this['level']=$level;
       }
    }

    public function setOutput()
    {
        $output = \PMVC\get($this,'output','debug_console');
        if (p\getOption(_VIEW_ENGINE)==='json') {
            $output = 'debug_store';
        }
        if (!is_object($output)) {
            $outputParam = [];
            if (isset($this['level'])) {
                $outputParam['level'] = $this['level'];
            }
            $output = p\plug( $output, $outputParam);
        }
        if (empty($output)) {
            return !trigger_error('[PMVC:PlugIn:Debug:getOutput] Get Output failded.',
                E_USER_WARNING
            );
        }
        if (!$output->is(__NAMESPACE__.'\DebugDumpInterface')) {
            return !trigger_error('['.get_class($output).'] is not a valid debug output object,'.
                'expedted DebugDumpInterface. '.print_r($output,true),
                E_USER_WARNING
            );
        }
        $this['output'] = $output;
        $this->_output = $output;
    }

    public function getOutput()
    {
        if (!$this->_output) {
            $this->setOutput();
        }
        return $this->_output;
    }

    public function d()
    {
        http_response_code(\PMVC\getOption('httpResponseCode',500));
        $a = func_get_args();
        if ($this->isException($a[0]) || (1===count($a) && is_string($a[0]))) {
            $tmp = $a[0];
        } else {
            ob_start();
            call_user_func_array('var_dump', $a);
            $tmp=ob_get_contents();
            ob_end_clean();
        }
        if (!$this->run) {
            $this->run=true;
            $this->dump($tmp);
            $this->run=false;
        }
    }

    public function parseTrace($raw, $slice = 0)
    {
        if ($slice) {
            $raw = array_slice($raw, $slice);
        }
        $arr = [];
        $i=1;
        $this->_detectTraceErrorLevel = null;
        foreach ($raw as $k=>$v) {
            $args = (!empty($v['args'])) ? $this->parseArgus($v['args']) : '';
            $name = $v['function'];
            $file = '';
            if (isset($v['file'])) {
                $file = '['.basename($v['file']).'] ';
            }
            if ('handleError'===$name) {
                $this->_detectTraceErrorLevel = 'error';
            }
            if (!empty($v['object'])) {
                $name = get_class($v['object']).$v['type'].$name;
                unset($v['object']);
            }
            unset($v['args']);
            unset($v['type']);
            $arr[$i.': '.$file.$name.'('.$args.')'] =$v;
            $i++;
        }
        $raw = null;
        unset($raw, $k, $v);
        return $arr;
    }

    public function dump($content)
    {
        $console=$this->getOutput();
        if (!$console) {
            return;
        }
        if ($this->isException($content)) {
            $message = $content->getMessage();
            $trace = $this->parseTrace($content->getTrace());
            $errorLevel = 'error';
        } else {
            $message =& $content;
            $trace = $this->parseTrace(debug_backtrace(), 7);
            $errorLevel = $this->_detectTraceErrorLevel;
            if (is_null($errorLevel)) {
                $errorLevel = 'debug';
            }
        }
        $json = \PMVC\fromJson($message);
        if (!is_array($json) && !is_object($json)) {
            $json = $message;
        }
        $console->dump($json, $errorLevel);
        unset($content, $message, $json);
        $console->dump($trace, 'trace');
        unset($trace, $console);
    }

    /**
     * Check is exception
     * @param object $object
     */
    public function isException($object) 
    {
        if ( is_a($object, 'Exception')
          || is_a($object, 'Error')
        ) {
            return true;
        } else {
            return false;
        }
    }
    
    public function objToStr($o)
    {
        if (is_object($o)) {
            $o = 'class '.get_class($o);
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
        $b=[];
        $console=$this->getOutput();
        for ($i=0, $j=count($a);$i<$j;$i++) {
            if (is_object($a[$i])) {
                $param = 'class '.get_class($a[$i]);
            } elseif (is_array($a[$i])) {
                $clone = array_merge([],$a[$i]);
                $param = key($clone).' => '. $this->objToStr(reset($clone));
                $param ='array '.$param;
            } elseif (is_null($a[$i])) {
                $param ='NULL';
            } else {
                $param = (string)$a[$i];
            }
            $b[] = $console->escape(mb_substr($param, 0, $this['truncate']));
        }
        return join(', ', $b);
    }

}
