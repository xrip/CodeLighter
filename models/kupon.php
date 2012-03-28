<?php
class kupon extends orm
{
    const table = __CLASS__;

	public static function all( $limit = FALSE, $start = 0 )
	{
		$records = array();

		$SQL = "SELECT * FROM ".self::table;

		if ( $limit !== FALSE )
		{
			$SQL .= " LIMIT ".$start.", ".$limit;
		}
		
		$results = Controller::instance()->db->query($SQL)->result_array();

		foreach ($results as $result)
		{
			$records[] = new static($result);
		}

		return $records;
	}


}
