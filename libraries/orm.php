<?php if (!defined('CODELIGHTER')) exit('No direct script access allowed');
/**
 * ORM for CodeLighter
 *
 * @author xrip <xrip@xrip.ru>
 * @copyright 2010-2012 xrip
 * @package Database
 * @require MySQL Database utilities for CodeLighter
 * @require CodeLighter
 * @version 1.0
 */

abstract class orm
{
    const table = __CLASS__;

    protected $fields = array();

    /** Orm results fabric
     * @todo refactor this! for additional where conditions
     * @static
     * @param $field
     * @param $values
     * @return array
     */
    public static function __callStatic( $field, $values )
    {
        $records = array();

        $SQL = "SELECT * FROM " . static::table . " WHERE " . static::table . "_" . $field . " = '" . $values[0] . "'";

        $data = Framework::instance()->db->query($SQL)->result_array();

        foreach ($data as $result)
        {
            $records[] = new static($result);
        }

        return $records;
    }

    public function __construct( $data = NULL )
    {
        if ( is_array($data) !== FALSE )
        {
            $this->fields = $data;
        }
        else {
            $result = Framework::instance()->db->query("DESCRIBE `" . '' . static::table . "`")->result();

            foreach ($result as $field)
            {
                $this->fields[$field->Field] = "";
            }
        }
    }

    public function __get( $field )
    {
        $field = static::table . '_' . $field;

        if ( isset($this->fields[$field]) === FALSE )
        {
            throw new Exception('Can\'t get database field "' . $field . '". Field does not exists!');
        }

        return $this->fields[$field];
    }


    public function __set( $field, $value )
    {
        $field = static::table . '_' . $field;

        if ( isset($this->fields[$field]) === FALSE )
        {
            throw new Exception('Can\'t set database field "' . $field . '". Field does not exists!');
        }

        $this->fields[$field] = $value;
    }

    public function save()
    {
        if ( is_numeric($this->id) === FALSE )
        {
            return $this->id = Framework::instance()->db->insert(static::table, $this->fields);
        }
        else
        {
            return Framework::instance()->db->update(static::table, $this->fields, array(static::table . '_id' => $this->id));
        }
    }

    public function delete()
    {
        return Framework::instance()->db->delete(static::table, array(static::table . '_id' => $this->id));
    }

}