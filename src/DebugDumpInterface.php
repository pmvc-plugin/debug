<?php
namespace PMVC\PlugIn\debug;

interface DebugDumpInterface
{
    public function escape($s);
    public function dump($p, $type='info');
}
