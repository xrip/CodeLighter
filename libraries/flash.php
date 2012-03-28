<?php if (!defined('CODELIGHTER')) exit('No direct script access allowed');
/*
   Class: Flash

   Purpose of this service is to make some data available across pages. Flash
   data is available on the next page but deleted when execution reach its end.

   Usual use of Flash is to make possible that current page pass some data
   to the next one (for instance success or error message before HTTP redirect).

   Example:
   > Flash::set('errors', 'Blog not found!');
   > Flass::set('success', 'Blog have been saved with success!');
   > Flash::get('success');

   Flash service as a concep is taken from Rails. This thing is really useful!
*/

final class Flash
{
        const SESSION_KEY = 'flash_vars';
        
        private static $_previous = array(); // Data that prevous page left in the Flash

        /*
           Method: init
           This function will read flash data from the $_SESSION variable
           and load it into $this->previous array
        */
        public static function init()
        {
                // Get flash data...
                if (!empty($_SESSION[self::SESSION_KEY]) and is_array($_SESSION[self::SESSION_KEY]))
                        self::$_previous = $_SESSION[self::SESSION_KEY];

                $_SESSION[self::SESSION_KEY] = array();
        }

        /*
           Method: get
           Return specific variable from the flash. If value is not found NULL is
           returned
        */
        public static function get($var_name)
        {
                return isset(self::$_previous[$var_name]) ? self::$_previous[$var_name]: null;
        }

        /*
           Method: set
           Add specific variable to the flash. This variable will be available on the
           next page unlease removed with the removeVariable() or clear() method
        */
        public static function set($var_name, $var_value)
        {
                $_SESSION[self::SESSION_KEY][$var_name] = $var_value;
        }

        /*
           Method: clear
           Call this function to clear flash. Note that data that previous page
           stored will not be deleted - just the data that this page saved for
           the next page
        */
        public static function clear()
        {
                $_SESSION[self::SESSION_KEY] = array();
        }

} // end Flash class