<?php declare(strict_types=1); 

if (!version_compare(phpversion(),'7.4.0','>='))
    die("PHP must be 7.4.0 or greater (you have ".PHP_VERSION.PHP_EOL);
if (PHP_INT_SIZE !== 8) die("64-bit PHP required".PHP_EOL);

if (!function_exists('mb_internal_encoding'))
    die("PHP mbstring Extension Required".PHP_EOL);
mb_internal_encoding("UTF-8");

error_reporting(E_ALL);

define('Andromeda',true); // entry-points
define('andromeda_version','0.0.1-alpha');

define('ROOT',__DIR__.'/');

// requires zend.assertions=1 in php.ini
ini_set('assert.active','1');
ini_set('assert.exception','1');

require_once(ROOT.'/../vendor/autoload.php');

/** @var float measure total autoloader time */
$autoloader_time = 0.0;
/** Returns the total measured autoloader time since the last call */
function get_autoloader_time() : float { 
    global $autoloader_time;
    /** @var float */
    $retval = $autoloader_time;
    $autoloader_time = 0; // reset
    return $retval; 
}

// the "almost PSR-4 compliant" autoloader?
// PHP's default seems to require lowercase files
spl_autoload_register(function(string $class)
{
    $start_time = hrtime(true);
    if (strpos($class,"Andromeda\\") !== 0) return;
    $class = substr($class, 10); // strlen("Andromeda\\")
    
    // usually a file is a single class
    $path = ROOT.str_replace("\\","/",$class).'.php';
    if (file_exists($path)) include_once($path);
    else
    {    // a file can also be a namespace
        if (($lpos = strrpos($class, "\\")) !== false)
            $class = substr($class, 0, $lpos);
        $path = ROOT.str_replace("\\","/",$class).'.php';
        if (file_exists($path)) include_once($path);
    }
    global $autoloader_time;
    $autoloader_time += (hrtime(true)-$start_time)/1e9;
});
