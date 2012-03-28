<?php define('CODELIGHTER', '2.0');
// ---------------------------------------------------
//  Framework directories
// ---------------------------------------------------
// all constants that you can define before to costumize your framework
if (!defined('CORE_ROOT'))          define('CORE_ROOT',   __DIR__);
if (!defined('COMMON_PATH'))        define('COMMON_PATH', CORE_ROOT);
if (!defined('APP_PATH'))           define('APP_PATH',    COMMON_PATH);
if (!defined('BASE_URL'))           define('BASE_URL',    'http://'.dirname($_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']).'/');
if (!defined('HELPER_PATH'))        define('HELPER_PATH', CORE_ROOT.'/helpers');

// ---------------------------------------------------
//  Framework init...
// ---------------------------------------------------

// Debug
if (!defined('DEBUG'))              define('DEBUG', TRUE);

// setting default controller and default action
if (!defined('DEFAULT_CONTROLLER')) define('DEFAULT_CONTROLLER', 'welcome');
if (!defined('DEFAULT_ACTION'))     define('DEFAULT_ACTION', 'index');

// setting error display depending on debug mode or not
error_reporting((DEBUG ? E_ALL : 0));

// no more quotes escaped with a backslash
// set_magic_quotes_runtime(0);


// ---------------------------------------------------
//  Set the ULR routing
// ---------------------------------------------------
// Example:
// 
// $routes['/blog/:num/comment/:num/delete'] = 'blog/deleteComment/$1/$2'; 
// means  visiting /blog/5/comment/42/delete would call BlogController::deleteComment(5,42)
$routes['/']      = DEFAULT_CONTROLLER.'/'.DEFAULT_ACTION;
$routes['/(.*?)'] = DEFAULT_CONTROLLER.'/$1';

// ---------------------------------------------------
//  Set database setting
// ---------------------------------------------------
$db['default']['dbtype'] = 'mysql';
$db['default']['dbhost'] = 'localhost';
$db['default']['dbuser'] = 'root';
$db['default']['dbpass'] = '';
$db['default']['dbname'] = 'test';
//$db['default']['cache']  = true;


// ---------------------------------------------------
//  Define framework configuration
// ---------------------------------------------------

$config['db']       = $db;

// ---------------------------------------------------
//  Start framework execution
// ---------------------------------------------------
include_once CORE_ROOT . '/CodeLighter.php';

// Initialize framework.
Framework::configure($config);

// Here we go!
Dispatcher::addRoute($routes);
Dispatcher::dispatch();

