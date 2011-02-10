<?php

require_once(dirname(__FILE__) . "/../../../simpletest/autorun.php");
require_once(dirname(__FILE__) . "/../pageparser.class.php");

class PageParserTest extends UnitTestCase
{
	public function testSetMethod()
	{
		$random_string = md5(rand(0,1000));

		$random_array = array(
			"lala" => md5(rand(0,1000)),
			"lulu" => md5(rand(0,1000)),
		);

		$data = array();

		$pp = new PageParser();
		$pp	-> set($random_string) 
			-> save($data["random_string"])
			-> set("")
			-> save($data["empty_string"])
			-> set($random_array)
			-> save($data["random_array"]);

		$this->assertEqual($data["random_string"],$random_string);
		$this->assertEqual($data["empty_string"],"");
		$this->assertEqual($data["random_array"],array_values($random_array));
	}

	public function testSetInConstruct()
	{
		$random_string = md5(rand(0,1000));

		$data = array();

		$pp = new PageParser($random_string);
		$pp -> save($data);

		$this->assertEqual($data,$random_string);
	}

	public function testDOMFindMethod()
	{
		$str = "
			<div id='main'>
				<div>
					<div>lala</div>
				</div>
				<div>lala</div>
			</div>
			<div>lala</div>
		";

		$result_str = "
				<div>
					<div>lala</div>
				</div>
				<div>lala</div>
		";

		$data = array();

		$pp = new PageParser($str);
		$pp	-> DOMFind("/<div[^<>]*id\s*=\s*[\"']?main[\"']?[^<>]*>/","/<\/div>/","/<div[^<>]*>/")
			-> save($data);

		$this->assertEqual(trim($data),trim($result_str));
	}
		
}

