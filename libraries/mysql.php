<?php if (!defined('CODELIGHTER')) exit('No direct script access allowed');
/**
 * MySQL Database utilities for CodeLighter
 * 
 * @author xrip <xrip@xrip.ru>
 * @copyright 2010-2012 xrip
 * @package Database
 * @require CodeLighter
 * @version 2.0
 * @TODO: Transform to mysqli
 */
 
class DBException extends Exception
{
    public function __construct( $message, $code = 0 )
    {
        $this->message[] = mysql_error();
        $this->message[] = $this->hightlight($message);
    }

    private function hightlight( $sql )
    {
        $sql = nl2br($sql);
        
        // Hightlight symbols
        $sql = preg_replace("#(\(|\)|\,|\=|\.|-|\+|\!|\@)#si", "<span style='color: navy'>$1</span>", $sql);

        // Hightlight digits
        $sql = preg_replace("#([0-9]+)#si", "<span style='color: orange'>$1</span>", $sql);
        
        // Hightlight keywords
        $sql = preg_replace("#(SELECT|UPDATE|INSERT|DELETE|DROP|TRUNCATE|FROM|WHERE|IN|AS|JOIN|INNER|LEFT|RIGHT|LIMIT|GROUP BY|ORDER BY|ON|HAVING|COUNT|MIN|MAX)\s#si", "<strong style='color: blue'>$1</strong>&nbsp;", $sql);
    
        return $sql;
    }
}

class MySQL {
    // mysql link
    protected $link;

    
    // cache vars
    protected $cache = FALSE;
    protected $cache_time = 3600;
    protected $cache_dir = './cache';

    /**
     * Connect to MySql database
     * @param $config
     * @throws DBException
     */
    public function __construct( $config )
    {

        $this->cache      = isset($config['cache'])      ? $config['cache']      : FALSE ;
        $this->cache_time = isset($config['cache_time']) ? $config['cache_time'] : 3600;
        $this->cache_dir  = isset($config['cache_dir'])  ? $config['cache_dir']  : './cache';

        try 
        {
                if ( ($this->link = @mysql_connect($config['dbhost'], $config['dbuser'], $config['dbpass'])) === FALSE )
                {
                    throw new DBException("Can't connect to ".$config['dbuser']."@".$config['dbhost']);
                }
        
                if ( mysql_select_db($config['dbname'], $this->link) === FALSE )
                {
                    throw new DBException("Can't connect to database ".$config['dbname']);
                }
        }
        catch ( DBException $e )
        {
            if ( $this->cache === FALSE )  
            {
                framework_exception_handler($e);
                exit();
            }
        }
    }


    /**
     * Set Cache on/off
     * @param $bool
     * @return
     */
    public function cache( $bool )
    {
        return $this->cache = $bool;
    }


    /**
     * Set Cache time
     * @param $seconds
     * @return
     */
    public function cache_time( $seconds )
    {
        return $this->cache_time = $seconds;    
    }


    /**
     * Set Cache Directory
     * @param $cache_dir
     * @return
     */
    public function cache_dir( $cache_dir )
    {
        return $this->cache_dir = $cache_dir;    
    }


    /**
     * DB class method call to the Query child class.
     * Returns an object.
     * @param $query
     * @return \CachedQuery|\Query
     */
    public function Query( $query )
    {
        if ( ( $this->cache == TRUE ) AND ( strtoupper(substr(trim($query), 0, 6))  == "SELECT" ) ) 
        {
            return new CachedQuery($query, $this->link, $this->cache_time, $this->cache_dir);
        }
        else
        {
            return new Query($query, $this->link);        
        }
    }


    /**
     * Inserts data into database
     * @param $table_name
     * @param $data
     * @return int
     */
    public function Insert( $table_name, $data )
    {
        $keys     = array_keys($data);
        $values = array_map(array(&$this, 'Clean'), array_values($data));
        
        $SQL = "INSERT INTO ".$table_name." (".join(', ', $keys).") VALUES ('".join("', '", $values)."')";        
        
        $this->query($SQL);
        
        return $this->insert_id();
    }

    /**
     * Updates data in database
     * @param $table_name
     * @param $data
     * @param $conditions
     * @return int
     */
    public function Update( $table_name, $data, $conditions )
    {
        $update = array();
        
        foreach ($data as $key => $value)
        {
            $update[] = $key." = '".$this->clean($value)."'";
        }
        
        $where = array();
        
        foreach ($conditions as $key => $value)
        {
            $where[] = $key."='".$this->clean($value)."'";
        }
        
        $SQL = "UPDATE ".$table_name." SET ".join(', ', $update)." WHERE ".join(' AND ', $where);

        $this->query($SQL);
        
        return $this->affected_rows();
    }

    /**
     * Deletes data from database
     * @param $table_name
     * @param $conditions
     * @return int
     */
    public function Delete( $table_name, $conditions )
    {
        $where = array();
        
        foreach ($conditions as $key => $value)
        {
            $where[] = $key."='".$this->clean($value)."'";
        }
        
        $SQL = "DELETE FROM ".$table_name." WHERE ".join(' AND ', $where);
        $this->query($SQL);
        
        return $this->affected_rows();
    }

    /**
     * simple method for sanitizing SQL query strings.
     * @param $string
     * @return string
     */
    public function Clean( $string )
    {
        if ( get_magic_quotes_gpc() )
        {
            $string = stripslashes($string);
        }
        return mysql_real_escape_string($string);
    }

    
    /**
     * Used to get the last inserted record
     * @return int
     */
    public function insert_id()
    {
        return mysql_insert_id($this->link);
    }
    

    /**
     * Used to get the last affected rows
     * @return int
     */
    public function affected_rows()
    {
        return mysql_affected_rows($this->link);
    }


    /**
     *   Close mysql connection
     */
    public function __destruct()
    {
        mysql_close($this->link);
    }
    
}

/**
 * Query child class.
 * Called exclusively by the DB parent class in the Query method
 */
class Query
{
    protected $result = array();
    protected $pointer = 0;

    /**
     * Create query object
     * @param $query
     * @param $link
     * @throws DBException
     */
    public function __construct( $query, $link )
    {
        $resource = mysql_query($query, $link);

        if ( is_resource($resource) ) // SELECT, SHOW, DESCRIBE, EXPLAIN
        {
            while ( $row = mysql_fetch_object($resource) ) 
            {
                $this->result[] = $row;
            }    
            mysql_free_result($resource);
        } 
        elseif ( $resource === TRUE ) // INSERT, UPDATE, DELETE, DROP, etc,
        {
            return mysql_affected_rows($link);
        }
        else 
        {
            throw new DBException($query); // ERROR
        }

        return false;
    }


    /** Return mysql objects set
     * @return array
     */
    public function Result()
    {
        return $this->result;
    }

    /** Return mysql array set
     * @return array
     */
    public function Result_array()
    {
        $result = array();
        reset($this->result);
        
        while ($current = current($this->result) )
        {
            next($this->result);
            $result[] = get_object_vars($current);
        }
        return $result;
    }
    

    public function Row()
    {
        if (count($this->result)) 
        {
            return $this->result[$this->pointer++];
        }
         else 
        {
            return FALSE;
        }
    }
    
    
    public function Row_array()
    {
        return get_object_vars($this->result[$this->pointer++]);
    }    

    
    public function num_rows()
    {
        return count($this->result);
    }
}


/**
 * CachedQuery extended Query class with cacheing
 */
class CachedQuery extends Query
{
    public function __construct( $query, $link, $cache_time = 0, $cache_dir = '.' )
    {
        $cache_file = $cache_dir.'/'.md5($query).'.db';

        if ( file_exists($cache_file) AND ( time() < ( filemtime($cache_file) + $cache_time ) ) )
        {
            $this->result = unserialize( file_get_contents($cache_file) );
        } 
         else
        {
            parent::__construct($query, $link);
            file_put_contents($cache_file, serialize($this->result));                
        }
    }
    
}


