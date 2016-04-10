<?php

class MiscTest extends MyBBIntegratorTestCase {

	const USER_ID_FOR_POSTHASH_1 = 1;
	const USER_ID_FOR_POSTHASH_2 = 2;

	const MD5_LENGTH = 32;

	public function testGeneratePostHashWithoutUserID() {
		$posthash = $this->mybb_integrator->generatePosthash();
		$posthash2 = $this->mybb_integrator->generatePosthash();
		
		$this->assertEquals(self::MD5_LENGTH, strlen($posthash));
		$this->assertEquals(self::MD5_LENGTH, strlen($posthash2));
		$this->assertNotEquals($posthash, $posthash2);
	}

	public function testGeneratePostHashWithUserID() {
		$posthash = $this->mybb_integrator->generatePosthash(self::USER_ID_FOR_POSTHASH_1);
		$posthash2 = $this->mybb_integrator->generatePosthash(self::USER_ID_FOR_POSTHASH_2);
		
		$this->assertEquals(self::MD5_LENGTH, strlen($posthash));
		$this->assertEquals(self::MD5_LENGTH, strlen($posthash2));
		$this->assertNotEquals($posthash, $posthash2);
	}

	public function testParseString_SimpleMessage() {
		$message = "Hello Test";
		$expected_message = "Hello Test";
		
		$parsed_message = $this->mybb_integrator->parseString($message);
		
		$this->assertEquals(
			$parsed_message,
			$expected_message
		);
	}

	public function testParseString_HTMLMessage() {
		$message = "<h1>Hello <b>Test</b></h1><script type=\"text/javascript\"></script>";
		$expected_message = "<h1>Hello <b>Test</b></h1>&lt;script type=\"text/javascript\"&gt;&lt;/script&gt;";
		$parsed_message = $this->mybb_integrator->parseString($message, array('allow_html' => 1));
		
		$this->assertEquals(
			$parsed_message,
			$expected_message
		);
	}
}