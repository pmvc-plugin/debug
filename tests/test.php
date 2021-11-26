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
        $debug['output'] = \PMVC\plug('output', [
            _CLASS => __NAMESPACE__ . '\fakeOutput',
        ]);
        $o = $debug->getOutput();
        $this->haveString('output', print_r($o, true));
    }

    public function testParseArgus()
    {
        $debugObj = new debug();
        \PMVC\InternalUtility::setPlugInConfig($debugObj, [
            'truncate' => 2,
            'output' => \PMVC\plug('fakeOutput', [
                _CLASS => __NAMESPACE__ . '\fakeOutput',
            ]),
        ]);
        $debugObj->init();

        $o = \PMVC\plug('unit')->call_private(
            __NAMESPACE__ . '\debug',
            '_parseArgus',
            [['xxx'], $debugObj['output']],
            $debugObj
        );
        $this->assertEquals('xx', $o);
    }

    public function testDump()
    {
        $debug = \PMVC\plug($this->_plug, [
            'truncate' => 2,
        ]);
        $debug['output'] = \PMVC\plug('output', [
            _CLASS => __NAMESPACE__ . '\fakeOutput',
        ]);
        $result = $debug->d(['a'], ['b']);
        $this->assertEquals([['a'], ['b']], $result);
    }
}

class fakeOutput extends \PMVC\PlugIn implements DebugDumpInterface
{
    public function escape($string, $type = null)
    {
        return $string;
    }
    public function dump($p, $type = 'info')
    {
    }
}
