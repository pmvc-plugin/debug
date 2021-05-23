<?php
namespace PMVC\PlugIn\debug;

use PMVC\TestCase;

class DebugConsoleTest extends TestCase
{
    private $_plug = 'debug';

    function testDebugConsole()
    {
        ob_start();
        print_r(\PMVC\plug($this->_plug));
        $output = ob_get_contents();
        ob_end_clean();
        $this->haveString($this->_plug, $output);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    function testGetOutput()
    {
        $debug = \PMVC\plug($this->_plug);
        $debug['output'] = \PMVC\plug(
            'output', [
            _CLASS=>__NAMESPACE__.'\fakeOutput'
            ]
        );
        $o = $debug->getOutput();
        $this->haveString('output', print_r($o, true));
    }

    public function testParseArgus()
    {
        $debug = \PMVC\plug(
            $this->_plug, [
            'truncate'=> 2 
            ]
        );
        $debug['output'] = \PMVC\plug(
            'output', [
            _CLASS=>__NAMESPACE__.'\fakeOutput'
            ]
        );
        
        $debugObj = \PMVC\InternalUtility::plugInStore($this->_plug);

        $o = \PMVC\plug('unit')->callPrivate(
          __NAMESPACE__.'\debug',
          '_parseArgus',
          [['xxx'], $debug['output']], 
          $debugObj
        );
        $this->assertEquals('xx', $o);
    }

    public function testDump()
    {
        $debug = \PMVC\plug(
            $this->_plug, [
            'truncate'=> 2 
            ]
        );
        $debug['output'] = \PMVC\plug(
            'output', [
            _CLASS=>__NAMESPACE__.'\fakeOutput'
            ]
        );
        $result = $debug->d(['a'], ['b']);
        $this->haveString('[0] => a', $result);
        $this->haveString('[0] => b', $result);
    }
}

class fakeOutput
    extends \PMVC\PlugIn
    implements DebugDumpInterface
{
    public function escape($string)
    {
        return $string;
    }
    public function dump($p,$type='info')
    {
    }
}

