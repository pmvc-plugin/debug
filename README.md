[![Latest Stable Version](https://poser.pugx.org/pmvc-plugin/debug/v/stable)](https://packagist.org/packages/pmvc-plugin/debug) 
[![Latest Unstable Version](https://poser.pugx.org/pmvc-plugin/debug/v/unstable)](https://packagist.org/packages/pmvc-plugin/debug) 
[![CircleCI](https://circleci.com/gh/pmvc-plugin/debug/tree/master.svg?style=svg)](https://circleci.com/gh/pmvc-plugin/debug/tree/master)
[![License](https://poser.pugx.org/pmvc-plugin/debug/license)](https://packagist.org/packages/pmvc-plugin/debug)
[![Total Downloads](https://poser.pugx.org/pmvc-plugin/debug/downloads)](https://packagist.org/packages/pmvc-plugin/debug) 

PMVC debug tool 
===============

## Debug output plugin
   1. Debug for browser console
      * https://github.com/pmvc-plugin/debug_console
   1. Debug with json output (API... etc view.)
      * https://github.com/pmvc-plugin/debug_store
      * Use with view engine
   1. Use with command line (Bash ... etc shell script)
      * https://github.com/pmvc-plugin/debug_cli

## How to Trigger debug
   * http -> $_REQUEST['--trace']
   * cli -> -t
      * if set -t and value is empty will force level to 'trace' 
         * such as "pmvc -t"
      * pmvc -tdebug  # it mean set to debug level.
      * pmvc -t debug # same
   * hardcode -> \PMVC\plug('debug')->setLevel('xxx');
   * PHPUnit -> https://github.com/pmvc-plugin/dev/blob/master/tests/DevWithPhpUnitTest.php

## Install with Composer
<details><summary>CLICK TO SEE</summary><p>

### 1. Download composer
   * mkdir test_folder
   * curl -sS https://getcomposer.org/installer | php

### 2. Install Use composer.json or use command-line directly
#### 2.1 Install Use composer.json
   * vim composer.json
```
{
    "require": {
        "pmvc-plugin/debug": "dev-master"
    }
}
```
   * php composer.phar install

#### 2.2 Or use composer command-line
   * php composer.phar require pmvc-plugin/debug

   or
   
   * composer require pmvc-plugin/debug

</p></details>
