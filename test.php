<?php
namespace PMVC\PlugIn\debug;

use PHPUnit_Framework_TestCase;

\PMVC\Load::plug(['debug'=>null],['../']);

class DebugConsoleTest extends PHPUnit_Framework_TestCase
{
    private $_plug = 'debug';

    function testDebugConsole()
    {
        ob_start();
        print_r(\PMVC\plug($this->_plug));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertContains($this->_plug,$output);
    }

    function testGetOutput()
    {
        $debug = \PMVC\plug($this->_plug);
        $debug['output'] = \PMVC\plug('output',[
            _CLASS=>__NAMESPACE__.'\fakeOutput'
        ]);
        $o = $debug->getOutput();
        $this->assertContains('output',print_r($o,true));
    }
}

class fakeOutput
    extends \PMVC\PlugIn
    implements DebugDumpInterface
{
    public function escape($string){}
    public function dump($p,$type='info'){}
}

