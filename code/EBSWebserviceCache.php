<?php

/**
 * Every X minutes the token gets regenerated. Speeds up consecutive requests.
 */
class EBSWebserviceCache extends DataObject {

	public static $db = array(
		'Name' => 'text',
		'Token' => 'text'
	);

}
