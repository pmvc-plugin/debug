<?php
namespace PMVC\PlugIn\debug;
use PMVC as p;

\PMVC\l(__DIR__.'/src/DebugDumpInterface.php');

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\debug';

class debug extends p\PlugIn
{
    private $run=false;

    public function init()
    {
        if (empty($this['output'])) {
            $this['output'] = 'debug_console';
        }
    }

    public function getOutput()
    {
        $output = $this['output'];
        if (p\getOption(_VIEW_ENGINE)==='json') {
            $output = p\plug('debug_store');
        }
        if (!is_object($output)) {
            $output = $this['output'] = p\plug($output);
        }
        return $output;
    }

    public function d()
    {
        $a = func_get_args();
        if ($this->isException($a[0])) {
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
        $console->dump($message, $error_level);
        $content=null;
        unset($content, $message);
        $arr =array();
        $i=1;
        foreach ($d as $k=>$v) {
            $args = (!empty($v['args'])) ? $this->parseArgus($v['args']) : '';
            $name = $v['function'];
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
        unset($d);
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
                $param = $a[$i];
            }
            $b[] = $console->escape(substr($param, 0, 50));
        }
        return join(', ', $b);
    }

    public function getLevel($level)
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
            return 1;
        }
    }
}
