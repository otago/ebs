<?php

namespace OP;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ArrayList;

/**
 * Class used to respond with JSON requests. Supports debugging.
 */
class EBSResponse {

	private $code;
	private $content;
	private $error;
	private $raw;
	private $url;

	function __construct($content, $code, $url) {
		$this->code = $code;
		$this->content = null;
		$this->raw = $content;
		$this->url = $url;
		if ($code == 200 && is_string($content)) {
			$this->content = json_decode($content);
		} else if (is_string($content)) {
			$this->error = json_decode($content);
		}
	}

	/**
	 * JSON array of the result of the response
	 * @return json array
	 */
	public function Content() {
		return $this->content;
	}

	/**
	 * 400 error. 500 server error. 200 OK etc.
	 * @return int
	 */
	public function Code() {
		return $this->code;
	}

	/**
	 * Raw content
	 * @return string
	 */
	public function Raw() {
		return $this->raw;
	}

	/**
	 * Recursivity creates the SilverStripe dataobject representation of content
	 * @param mixed $array
	 * @return \DataObject|\DataList|null
	 */
	private function parseobject($array) {
		if (is_object($array)) {
			if (get_class($array) == 'DataObject') {
				return $array;
			}
			$do = DataObject::create();
			foreach (get_object_vars($array) as $key => $obj) {
				if ($key == '__Type') {
					$do->setField('Title', $obj);
				} else if (is_array($obj) || is_object($obj)) {
					$do->setField($key, $this->parseobject($obj));
				} else {
					$do->setField($key, $obj);
				}
			}
			return $do;
		} else if (is_array($array)) {
			$dataList = ArrayList::create();
			foreach ($array as $key => $obj) {
				$dataList->push($this->parseobject($obj));
			}
			return $dataList;
		}
		return null;
	}

	/**
	 * Returns SilverStripe object representations of content
	 * @return \DataObject|\DataList|null
	 */
	public function Data() {
		return $this->parseobject($this->content);
	}

	/**
	 * Returns SilverStripe object representations of content
	 * @return \DataObject|\DataList|null
	 */
	public function Errors() {
		return $this->parseobject($this->error);
	}

	/**
	 * Makes debugging the webservice more human readable
	 * @return HTML
	 */
	public function debug() {
		return '<dl>
				<dt><strong>URL</strong></dt>
				<dd>' . $this->url . '</dd>
				<dt><strong>Code</strong></dt>
				<dd>' . $this->code . '</dd>
				<dt><strong>Response</strong></dt>
				<dd><pre>' . $this->raw . '</pre></dd>
				<dt><strong>Errors</strong></dt>
				<dd>' . Debug::text(json_decode(json_encode($this->error), true)) . '</dd>
			  </dl>';
	}

}
