<?php
PMVC\Load::plug();
PMVC\addPlugInFolders(['../']);
class DebugConsoleTest extends PHPUnit_Framework_TestCase
{
    function testDebugConsole()
    {
        ob_start();
        $plug = 'debug';
        print_r(PMVC\plug($plug));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertContains($plug,$output);
    }
}
