<?php
namespace PMVC\PlugIn\debug;

interface DebugDumpInterface
{
    public function escape($s, $type = null);
    public function dump($p, $type = 'info');
}
