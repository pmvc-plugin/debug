<?php
namespace PMVC\PlugIn\debug;

use PMVC as p;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\debug';

\PMVC\l(__DIR__.'/src/DebugDumpInterface.php');

const INPUT_FIELD = '_trace';

/**
 * @parameters string  output   Debug output function [debug_console|debug_store|debug_cli]
 * @parameters string  truncate Debug truncate dump function parameter string lengths 
 * @parameters numeric level    Debug dump level 
 */
class debug extends p\PlugIn
{
    private $run=false;
    private $_output;

    public function init()
    {
        if (empty($this['output'])) {
            $this['output'] = 'debug_console';
        }
        if (empty($this['truncate'])) {
            $this['truncate'] = 100;
        }
        p\callPlugin(
            'dispatcher',
            'attach',
            [ 
                $this,
                'SetConfig__run_form_',
            ]
        );
        $this->setLevelType(\PMVC\value($_REQUEST,[INPUT_FIELD]), false);
    }

    public function getLevel($level, $default=1)
    {
        $levels =  [
            'trace'=>1,
            'debug'=>2,
            'info'=>3,
            'warn'=>4,
            'error'=>5
        ];
        if (isset($levels[$level])) {
            return $levels[$level];
        } else {
            return $default;
        }
    }

    public function setLevelType($level, $force=true)
    {
       if (!isset($this['level']) || $force) {
            $this['level']=$level;
       }
    }

    public function onSetConfig__run_form_($subject)
    {
        $subject->detach($this);
        $trace = p\value(
            p\getOption(_RUN_FORM),
            [INPUT_FIELD]
        );
        $this->setLevelType($trace, false);
    }

    public function setOutput()
    {
        $output = $this['output'];
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

    public function dump($content)
    {
        $console=$this->getOutput();
        if (!$console) {
            return;
        }
        if ($this->isException($content)) {
            $message = $content->getMessage();
            $d = $content->getTrace();
            $error_level = 'error';
        } else {
            $message =& $content;
            $d=debug_backtrace();
            $d=array_slice($d, 7);
            $error_level = 'debug';
        }
        $arr =array();
        $i=1;
        foreach ($d as $k=>$v) {
            $args = (!empty($v['args'])) ? $this->parseArgus($v['args']) : '';
            $name = $v['function'];
            if ('handleError'===$name) {
                $error_level = 'error';
            }
            if (!empty($v['object'])) {
                $name = get_class($v['object']).$v['type'].$name;
                unset($v['object']);
            }
            unset($v['args']);
            unset($v['type']);
            $arr[$i.':'.$name.'('.$args.')'] =$v;
            $i++;
        }
        $d=null;
        $console->dump($message, $error_level);
        $content=null;
        unset($d,$content, $message);
        $console->dump($arr, 'trace');
        $arr=null;
        unset($arr);
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

    public function isShow($runLevel, $showLevel)
    {
        return $this->getLevel($runLevel) >=
            $this->getLevel($showLevel);
    }
}
