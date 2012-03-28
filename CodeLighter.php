<?php if (!defined('CODELIGHTER')) exit('No direct script access allowed');
/**
 * CodeLighter
 *
 * @author xrip <xrip@xrip.ru>
 * @copyright 2009-2012 xrip
 * @package CodeLighter
 * @version 2.0
 */

if ( ! ini_get('session.auto_start') || strtolower(ini_get('session.auto_start')) == 'off') session_start();
define('FRAMEWORK_STARTING_MICROTIME', get_microtime());


/**
 * The Dispatcher main Core class is responsible for mapping urls /
 * routes to Controller methods. Each route that has the same number of directory
 * components as the current requested url is tried, and the first method that
 * returns a response with a non false / non null value will be returned via the
 * Dispatcher::dispatch() method. For example:
 *
 * A route string can be a literal url such as '/pages/about' or contain
 * wildcards (:any or :num) and/or regex like '/blog/:num' or '/page/:any'.
 * 
 * Dispatcher::addRoute(array(
 *  '/' => 'page/index',
 *  '/about' => 'page/about,
 *  '/blog/:num' => 'blog/post/$1',
 *  '/blog/:num/comment/:num/delete' => 'blog/deleteComment/$1/$2'
 * ));
 *
 * Visiting /about/ would call PageController::about(),
 * visiting /blog/5 would call BlogController::post(5)
 * visiting /blog/5/comment/42/delete would call BlogController::deleteComment(5,42)
 *
 * The dispatcher is used by calling Dispatcher::addRoute() to setup the route(s),
 * and Dispatcher::dispatch() to handle the current request and get a response.
 */

final class Dispatcher
{
    private static $routes = array();
    private static $params = array();
    private static $status = array();
    private static $requested_url = '';
    
    public static function addRoute($route, $destination = null)
    {
        if ($destination != null && !is_array($route))
        {
            $route = array($route => $destination);
        }

        self::$routes = array_merge(self::$routes, $route);
    }
    
    public static function splitUrl($url)
    {
        return preg_split('/\//', $url, -1, PREG_SPLIT_NO_EMPTY);
    }
    
    public static function dispatch($requested_url = null, $default = null)
    {
        // if no url passed, we will get the first key from the _GET array
        // that way, index.php?/controller/action/var1&email=example@example.com
        // requested_url will be equal to: /controller/action/var1
        if ($requested_url === null)
        {
            //$requested_url = count($_GET) >= 1 ? key($_GET) : '/';
            $pos = strpos($_SERVER['QUERY_STRING'], '&');

            if ($pos !== false)
            {
                $requested_url = substr($_SERVER['QUERY_STRING'], 0, $pos);
            }
            else
            {
                $requested_url = $_SERVER['QUERY_STRING'];
            }
        }

        // If no URL is requested (due to someone accessing admin section for the first time)
        // AND $default is setAllow for a default tab
        if ($requested_url == null && $default != null)
        {
            $requested_url = $default;
        }
        
        // requested url MUST start with a slash (for route convention)
        if (strpos($requested_url, '/') !== 0)
        {
            $requested_url = '/' . $requested_url;
        }
        
        self::$requested_url = $requested_url;
        
        // this is only trace for debuging
        self::$status['requested_url'] = $requested_url;
        
        // make the first split of the current requested_url
        self::$params = self::splitUrl($requested_url);
        
        // do we even have any custom routing to deal with?
        if (count(self::$routes) === 0)
        {
            return self::executeAction(self::getController(), self::getAction(), self::getParams());
        }
        
        // is there a literal match? If so we're done
        if (isset(self::$routes[$requested_url]))
        {
            self::$params = self::splitUrl(self::$routes[$requested_url]);

            return self::executeAction(self::getController(), self::getAction(), self::getParams());
        }
        
        // loop through the route array looking for wildcards
        foreach (self::$routes as $route => $uri)
        {
            // convert wildcards to regex
            if (strpos($route, ':') !== false)
            {
                $route = str_replace(':any', '(.?)', str_replace(':num', '([0-9]+)', $route));
            }

            // does the regex match?
            if (preg_match('#^'.$route.'$#', $requested_url))
            {
                // do we have a back-reference?
                if (strpos($uri, '$') !== false && strpos($route, '(') !== false)
                {
                    $uri = preg_replace('#^'.$route.'$#', $uri, $requested_url);
                }
                self::$params = self::splitUrl($uri);
                // we found it, so we can break the loop now!
                break;
            }
        }
        
        return self::executeAction(self::getController(), self::getAction(), self::getParams());
    } // dispatch
    
    public static function getCurrentUrl()
    {
        return self::$requested_url;
    }
    
    public static function getController()
    {
        // Check for settable default controller
        return isset(self::$params[0]) ? self::$params[0]: DEFAULT_CONTROLLER;
    }
        
    public static function getAction()
    {
        return isset(self::$params[1]) ? self::$params[1]: DEFAULT_ACTION;
    }
    
    public static function getParams()
    {
        return array_slice(self::$params, 2);
    }
    
    public static function getStatus($key=null)
    {
        return ($key === null) ? self::$status: (isset(self::$status[$key]) ? self::$status[$key]: null);
    }

    public static function executeAction($controller, $action, $params)
    {
        self::$status['controller'] = $controller;
        self::$status['action'] = $action;
        self::$status['params'] = implode(', ', $params);
        
        $controller_class = Inflector::camelize($controller);

        $method = new ReflectionMethod($controller_class, $action);

        if ( $method->isStatic()  )
        {
            return $controller_class::$action($params);
        }

        // get a instance of that controller
        if (class_exists($controller_class))
        {
            $controller = new $controller_class();
        }

        if ( ! $controller instanceof Controller)
        {
		    throw new Exception("Class '".$controller_class."' does not extends Controller class!");
        }

        // execute the action
        return $controller->execute($action, $params);
    }

} // end Dispatcher class


abstract class framework
{
    private static $instance;

    protected static $config;

    public function __construct()
    {
        self::$instance =& $this;
    }

    public static function &instance()
    {
        return self::$instance;
    }

    public static function configure($config)
    {
        self::$config = $config;
    }

    public static function getConfig($key)
    {
        return isset( self::$config[$key] ) ? self::$config[$key] : NULL;
    }
}

/**
 * The Controller class should be the parent class of all of your Controller sub classes
 * that contain the business logic of your application (render a blog post, log a user in,
 * delete something and redirect, etc).
 *
 * In the CodeLighter class you can define what urls / routes map to what Controllers and
 * methods. Each method can either:
 *
 * - return a string response
 * - redirect to another method
 */
class Controller extends framework
{
    // Lazy loading with magic __get
    public function __get($property)
    {
        switch ($property)
        {
            case 'load': return $this->load = new load();
            case 'db'  : return load::database();
        }

        // Pass to standard notice
        return $this->$property;
    }

    public function execute($action, $params)
    {
        // it's a private method of the class or action is not a method of the class
        if (substr($action, 0, 1) == '_' || ! method_exists($this, $action)) {
            throw new Exception("Action '{$action}' is not valid!");
        }
        call_user_func_array(array($this, $action), $params);
    }

} // end Controller class

class Model {

    private $_parent_name = '';

    /**
     * Constructor
     *
     * @access public
     * @param null $name
     */
    public final function __construct($name = NULL)
    {
        // If the magic __get() or __set() methods are used in a Model references can't be used.
        $this->_assign_libraries( (method_exists($this, '__get') OR method_exists($this, '__set')) ? FALSE : TRUE );
        
        // We don't want to assign the model object to itself when using the
        // assign_libraries function below so we'll grab the name of the model parent
        $this->_parent_name = strtolower(get_class($this)) == strtolower($name)?:$name;
    }

    /**
     * Assign Libraries
     *
     * Creates local references to all currently instantiated objects
     * so that any syntax that can be legally used in a controller
     * can be used within models.
     *
     * @access private
     * @param bool $use_reference
     */
    private function _assign_libraries($use_reference = TRUE)
    {
        $controller = Controller::instance();

        foreach (array_keys(get_object_vars($controller)) as $key)
        {
            if ( ! isset($this->$key) AND $key != $this->_parent_name)
            {           
                // In some cases using references can cause
                // problems so we'll conditionally use them
                if ($use_reference == TRUE)
                {
                    // Needed to prevent reference errors with some configurations
                    $this->$key =& $controller->$key;
                }
                else
                {
                    $this->$key = $controller->$key;
                }
            }
        }       
    }

}
// end Model class

/**
 * View system for CodeLighter
 *
 * @author xrip <z.xrip.z@gmail.com>
 * @copyright 2010, 2011, 2012 xrip
 * @package View
 * @require CodeLighter
 * @version 1.0
 */
class View
{
    /** View's variables
     * @var array
     */
    private $_vars = array();

    /**  View's filename with desired path
     * @var string
     */
    private $_file;

    /**  Construct our View file with or with out predefined variables
     * @param $file
     * @param array $vars
     * @throws Exception
     */

    public function __construct($file, $vars = array())
    {
        $this->_file = $file;

        if ( ! empty($vars) )
        {
            if ( is_object($vars) ) $vars = get_object_vars($vars);

            foreach ($vars as $key => $var)
            {
                $this->_vars[$key] = $var;
            }
        }
    }


    /**
     * Read assigned variable
     * ex: echo $view->var_name;
     *
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->_vars[$key];
    }


    /**
     * Assign an variable to our View
     * ex: $view->var_name = 'Something';
     *     $view->var_name = array('begin', ... , 'end');
     *
     * @param $key
     * @param $var
     */
    public function __set($key, $var)
    {
        $this->_vars[$key] = $var;
    }


    /**
     * Renderer our View to a string
     * ex: echo $view;
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }


    /**  Render our View and return it as string
     * @return string
     */
    private function render()
    {
        ob_start();

        extract($this->_vars);

        require $this->_file;

        $content = ob_get_clean();

        return $content;
    }

    /**  Render and display our View
     *
     */
    public function display()
    {
        echo $this->render();
    }
}
// end View class


class Load
{
    private static $_models   = array();
    private static $_views    = array();
    private static $_helpers  = array();

    private static function _searchFile($file)
    {
        // first look for file at app_path
        if ( file_exists(APP_PATH.'/'.ltrim($file, '/').'.php') )
        {
            return APP_PATH.'/'.ltrim($file, '/').'.php';
        }
        elseif ( file_exists(COMMON_PATH.'/'.ltrim($file, '/').'.php') )
        {
            return COMMON_PATH.'/'.ltrim($file, '/').'.php';
        }
        else
        {
            throw new Exception("File '".$file."' not found!");
        }
    }


    /**
     * Load model class from the model file (faster then waiting for the __autoload function)
     *
     * @param  string $model in CamelCase
     * @param bool|string $name
     * @throws Exception
     * @return void
     */
    public static function model($model, $name = FALSE)
    {
        if ( $name === FALSE ) $name = $model;

        if ( in_array($name, self::$_models) ) throw new Exception("Model '".$name."' allready loaded!");

        self::$_models[] = $name;

        Controller::instance()->$name = new $model($name);

    }

    /**
     *  Load view
     * @param $view
     * @param bool $name
     * @throws Exception
     * @return view
     */
    public static function view($view, $name = FALSE)
    {
        if ( $view === FALSE ) $name = $view;

        if ( in_array($view, self::$_views) ) throw new Exception("View '".$name."' allready loaded!");

        self::$_views[] = $name;

        return new view( self::_searchFile('views/'.$view) );
    }

    /**
     * Load all functions from the helper file
     *
     * syntax:
     * use_helper('Cookie');
     * use_helper('Number', 'Javascript', 'Cookie', ...);
     *
     * @param  string helpers in CamelCase
     * @return void
     */

    public static function helper()
    {
        $helpers = func_get_args();
        
        foreach ($helpers as $helper)
        {
            if (in_array($helper, self::$_helpers)) throw new Exception("Helper '".$helper."' allready loaded!");
            
            require_once(  self::_searchFile( HELPER_PATH.'/'.$helper) );

            self::$_helpers[] = $helper;
        }
    }


    /**
     * Database Loader
     *
     * @access  public
     * @param   string  $config array name
     * @param   string  $name in global CL scope
     * @throws Exception
     */
    public static function database($config = 'default', $name = 'db')
    {
        $db = Framework::getConfig('db');

        if ( empty($db[$config]) ) throw new Exception("No database config '".$config."' found!");

        return Framework::instance()->$name = new $db[$config]['dbtype']( $db[$config] );
    }
    
    // --------------------------------------------------------------------
}
// end Loader class


/**
 * The AutoLoader class is an object oriented hook into PHP's __autoload functionality. You can add
 * 
 * - Single Files AutoLoader::addFile('Blog','/path/to/Blog.php');
 * - Multiple Files AutoLoader::addFile(array('Blog'=>'/path/to/Blog.php','Post'=>'/path/to/Post.php'));
 * - Whole Folders AutoLoader::addFolder('path');
 *
 * When adding a whole folder each file should contain one class named the same as the file without ".php" (Blog => Blog.php)
 */
class AutoLoader
{
    protected static $files = array();
    protected static $folders = array();
    
    /**
     * AutoLoader::addFile('Blog','/path/to/Blog.php');
     * AutoLoader::addFile(array('Blog'=>'/path/to/Blog.php','Post'=>'/path/to/Post.php'));
     * @param mixed $class_name string class name, or array of class name => file path pairs.
     * @param mixed $file Full path to the file that contains $class_name.
     */
    public static function addFile($class_name, $file = null)
    {
        if ($file == null && is_array($class_name))
        {
            array_merge(self::$files, $class_name);
        }
        else
        {
            self::$files[$class_name] = $file;
        }
    }
    
    /**
     * AutoLoader::addFolder('/path/to/my_classes/');
     * AutoLoader::addFolder(array('/path/to/my_classes/','/more_classes/over/here/'));
     * @param mixed $folder string, full path to a folder containing class files, or array of paths.
     */
    public static function addFolder($folder)
    {
        if ( ! is_array($folder))
        {
            $folder = array($folder);
        }
        self::$folders = array_merge(self::$folders, $folder);
    }
    
    public static function load($class_name)
    {
        if (isset(self::$files[$class_name]))
        {
            if (file_exists(self::$files[$class_name]))
            {
                require self::$files[$class_name];

                return;
            }
        } else {
            foreach (self::$folders as $folder)
            {
                $folder = rtrim($folder, DIRECTORY_SEPARATOR);
                $file = $folder.'/'.$class_name.'.php';

                if (file_exists($file))
                {
                    require $file;
                    return;
                }
            }
        }
    }
    
} // end AutoLoader class

if ( ! function_exists('__autoload'))
{
    AutoLoader::addFolder(array(
				APP_PATH.'/'.'models',
                APP_PATH.'/'.'controllers',
				COMMON_PATH.'/'.'models',
                COMMON_PATH.'/'.'controllers',
				CORE_ROOT.'/'.'libraries'));

    function __autoload($class_name)
    {
        AutoLoader::load($class_name);
    }
}


final class Inflector 
{
    /**
     *  Return an CamelizeSyntaxed (LikeThisDearReader) from something like_this_dear_reader.
     *
     * @param string $string Word to camelize
     * @return string Camelized word. LikeThis.
     */
    public static function camelize($string)
    {
        return str_replace(' ','',ucwords(str_replace('_',' ', $string)));
    }

    /**
     * Return an underscore_syntaxed (like_this_dear_reader) from something LikeThisDearReader.
     *
     * @param  string $string CamelCased word to be "underscorized"
     * @return string Underscored version of the $string
     */
    public static function underscore($string)
    {
        return strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $string));
    }
    
    /**
     * Return an Humanized syntaxed (Like this dear reader) from something like_this_dear_reader.
     *
     * @param  string $string CamelCased word to be "underscorized"
     * @return string Underscored version of the $string
     */
    public static function humanize($string)
    {
        return ucfirst(str_replace('_', ' ', $string));
    }
}



// ----------------------------------------------------------------
//   global function
// ----------------------------------------------------------------

/**
 * create a real nice url like http://www.example.com/controller/action/params#anchor
 *
 * you can put many params as you want,
 * if a params start with # it is considerated a Anchor
 *
 * get_url('controller/action/param1/param2') // I always use this method
 * get_url('controller', 'action', 'param1', 'param2');
 *
 * @param string controller, action, param and/or #anchor
 * @return string
 */
function get_url()
{
    $params = func_get_args();
    if (count($params) === 1) return BASE_URL . $params[0];
    
    $url = '';
    foreach ($params as $param)
    {
        if (strlen($param))
        {
            $url .= $param{0} == '#' ? $param: '/'. $param;
        }
    }
    return BASE_URL . preg_replace('/^\/(.*)$/', '$1', $url);
}

/**
 * Get the request method used to send this page
 *
 * @return string possible value: GET, POST or AJAX
 */
function get_request_method()
{
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') return 'AJAX';
    else if ( ! empty($_POST)) return 'POST';
    else return 'GET';
}

/**
 * Redirect this page to the url passed in param
 * @param $url
 */
function redirect($url)
{
    header('Location: '.$url); exit;
}

/**
 * Encodes HTML safely for UTF-8. Use instead of htmlentities.
 * @param $string
 * @return string
 */
function html_encode($string)
{
    return htmlentities($string, ENT_QUOTES, 'UTF-8') ;
}

/**
 * Display a 404 page not found and exit
 */
function page_not_found()
{
    header("HTTP/1.0 404 Not Found");
    echo new View('404');
    exit;
}

function convert_size($num)
{
    if ($num >= 1073741824) $num = round($num / 1073741824 * 100) / 100 .' gb';
    else if ($num >= 1048576) $num = round($num / 1048576 * 100) / 100 .' mb';
    else if ($num >= 1024) $num = round($num / 1024 * 100) / 100 .' kb';
    else $num .= ' b';
    return $num;
}

// information about time and memory

function memory_usage()
{
    return convert_size(memory_get_usage());
}

function execution_time()
{
    return sprintf("%01.4f", get_microtime() - FRAMEWORK_STARTING_MICROTIME);
}

function get_microtime()
{
    $time = explode(' ', microtime());
    return doubleval($time[0]) + $time[1];
}

function odd_even()
{
    static $odd = true;
    return ($odd = !$odd) ? 'even': 'odd';
}

function even_odd()
{
    return odd_even();
}

/**
 * Provides a nice print out of the stack trace when an exception is thrown.
 *
 * @param Exception $e Exception object.
 */
function framework_exception_handler($e)
{
    if ( !DEBUG ) 
	{
		page_not_found();
	}

    echo '<title>Uncaught '.get_class($e).'</title>';
    echo '<style>h1,h2,h3,p,td {font-family: Verdana, serif; font-weight:lighter;} b {font-weight: bold;}</style>';
    echo '<p>Uncaught '.get_class($e).'</p>';

    if ( get_class($e) == 'DBException' )
    {
        list( $error, $query ) = $e->getMessage();
        $message = '<h1>Database Error!</h1>';
        $message.= '<div style="border: 1px solid #ccc; padding: 10px; width: 800px;">';
        $message.= '<p>'.$error.'</p>';    
        $message.= '<p style="color: #555">'.$query.'</p>';    
        $message.= '</div>';                                                    
    }
     else
    {
        $message = '<h1>'.$e->getMessage().'</h1>';
    }
	
    echo $message;

    $traces = $e->getTrace();

	$old_class = '';
    if ( count($traces) > 1 ) 
	{
        echo '<p><b>Trace in execution order:</b></p>'.
             '<pre style="font-family:Courier; line-height: 22px">';
        
        $level = 0;
        foreach ( array_reverse($traces) as $trace ) 
		{
            if ( isset($trace['class']) ) 
			{
				if ($trace['class'] != $old_class)
				{
					$level++;
					echo $trace['class'];
				}
				 else
				{
					echo str_repeat(' ', strlen($trace['class'])-2);
				}
				
				echo '&thinsp;&rarr;&thinsp;';
				$old_class = $trace['class'];
			}
                
            $args = array();
            if ( ! empty($trace['args']) ) 
			{
                foreach ($trace['args'] as $arg) 
				{
                    if (is_null($arg)) $args[] = 'null';
                    else if (is_array($arg))  $args[] = 'array['.sizeof($arg).']';
                    else if (is_object($arg)) $args[] = get_class($arg).' Object';
                    else if (is_bool($arg))   $args[] = $arg ? 'true' : 'false';
                    else if (is_int($arg))    $args[] = $arg;
                    else 
					{
                        $arg = htmlspecialchars(substr($arg, 0, 64));
                        if ( strlen($arg) >= 64 ) $arg .= '...';
                        $args[] = "'". $arg ."'";
                    }
                }
            }
            echo '<b>'.$trace['function'].'</b>('.implode(', ',$args).') ';
            echo 'called on line <i>'.( isset($trace['line']) ? $trace['line'] : 'unknown' ).'</i> ';
            echo 'in <code>'.( isset($trace['file']) ? $trace['file'] : 'unknown' )."</code>\n";
            echo str_repeat("  ", $level);
        }
        echo '</pre>';
    }
    echo "<p>Exception was thrown on line <code>"
         . $e->getLine() . "</code> in <code>"
         . $e->getFile() . "</code></p>";
    
    $dispatcher_status = Dispatcher::getStatus();
    $dispatcher_status['request method'] = get_request_method();
    debug_table($dispatcher_status, 'Dispatcher status');
    if ( ! empty($_GET)) debug_table($_GET, 'GET');
    if ( ! empty($_POST)) debug_table($_POST, 'POST');
    if ( ! empty($_COOKIE)) debug_table($_COOKIE, 'COOKIE');
    debug_table($_SERVER, 'SERVER');
}

function debug_table($array, $label, $key_label='Variable', $value_label='Value')
{
    echo '<h2>'.$label.'</h2>';
    echo '<table cellpadding="3" cellspacing="0" style="width: 800px; border: 1px solid #ccc">';
    echo '<tr><td style="border-right: 1px solid #ccc; border-bottom: 1px solid #ccc;">'.$key_label.'</td>'.
         '<td style="border-bottom: 1px solid #ccc;">'.$value_label.'</td></tr>';
    
    foreach ($array as $key => $value) {
        if (is_null($value)) $value = 'null';
        else if (is_array($value)) $value = 'array['.sizeof($value).']';
        else if (is_object($value)) $value = get_class($value).' Object';
        else if (is_bool($value)) $value = $value ? 'true' : 'false';
    //    else if (is_int($value)) $value = $value;
        else {
            $value = htmlspecialchars(substr($value, 0, 64));
            if (strlen($value) >= 64) $value .= ' &hellip;';
        }
        echo '<tr><td><code>'.$key.'</code></td><td><code>'.$value.'</code></td></tr>';
    }
    echo '</table>';

}

set_exception_handler('framework_exception_handler');

/**
 * This function will strip slashes if magic quotes is enabled so 
 * all input data ($_GET, $_POST, $_COOKIE) is free of slashes
 */
function fix_input_quotes()
{
    $in = array(&$_GET, &$_POST, &$_COOKIE);
    while (list($k,$v) = each($in)) {
        foreach ($v as $key => $val) {
            if (!is_array($val)) {
                 $in[$k][$key] = stripslashes($val); continue;
            }
            $in[] =& $in[$k][$key];
        }
    }
    unset($in);
} // fix_input_quotes

function base_url() 
{
    return BASE_URL;
}
    
if (get_magic_quotes_gpc()) {
    fix_input_quotes();
}

