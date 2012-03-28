<?php if (!defined('CODELIGHTER')) exit('No direct script access allowed');
/**
 * The Observer class allows for a simple but powerfull event system.
 * 
 * Example of watching/handling an event:
 *      // Connecting your event hangling function to an event.
 *      Observer::observe('page_edit_after_save', 'my_simple_observer');
 * 
 *      // The event handling function
 *      function my_simple_observer($page) {
 *          // do what you want to do
 *          var_dump($page);
 *      }
 * 
 * Example of generating an event:
 * 
 *      Observer::notify('my_plugin_event', $somevar);
 * 
 */
final class Observer
{
    static protected $events = array();

    /**
     * Allows an event handler to watch/handle for a spefied event.
     *
     * @param string $event_name    The name of the event to watch for.
     * @param string $callback      The name of the function handling the event.
     */
    public static function observe($event_name, $callback)
    {
        if ( ! isset(self::$events[$event_name]) )
        {
            self::$events[$event_name] = array();
        }

        self::$events[$event_name][$callback] = $callback;
    }

    /**
     * Allows an event handler to stop watching/handling a specific event.
     *
     * @param string $event_name    The name of the event.
     * @param string $callback      The name of the function handling the event.
     */
    public static function stopObserving($event_name, $callback)
    {
        if (isset(self::$events[$event_name][$callback]))
        {
            unset(self::$events[$event_name][$callback]);
        }
    }

    /**
     * Clears all registered event handlers for a specified event.
     *
     * @param string $event_name
     */
    public static function clearObservers($event_name)
    {
        self::$events[$event_name] = array();
    }

    /**
     * Returns a list of all event handlers handling a specified event.
     *
     * @param string $event_name
     * @return array An array of names for event handlers.
     */
    public static function getObserverList($event_name)
    {
        return ( isset(self::$events[$event_name]) ) ? self::$events[$event_name] : array();
    }

    /**
     * Generates an event with the specified name.
     *
     * Note: if your event does not need to process the return values from any
     *       observers, use this instead of getObserverList().
     *
     * @param string $event_name
     */
    public static function notify($event_name)
    {
        $args = array_slice(func_get_args(), 1); // remove event name from arguments

        foreach(self::getObserverList($event_name) as $callback)
        {
            // XXX For some strange reason, this works... figure out later.
            // @todo FIXME Make this proper PHP 5.3 stuff.
            $Args = array();
            foreach($args as $k => &$arg){
                $Args[$k] = &$arg;
            }
            call_user_func_array($callback, $args);
        }
    }
}