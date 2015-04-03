<?php

require __DIR__.'/../index.php';

class RequestTest extends PHPUnit_Framework_TestCase
{
	public function setUp() {}
	public function tearDown() {}

	public function testRoute()
	{
		$request = array(
			'host' => 'sub.test-domain.com',
			'https' => true
		);
		$routes = array(
			'test_host' => array(
				'match' => array(
					'host' => '*.test-domain.com'
				),
				'request' => array(
					'test_host_matched' => true
				)
			),
			'test_https' => array(
				'match' => array(
					'https' => true
				),
				'request' => array(
					'test_https_matched' => true
				)
			)
		);
		$request = \Schema\Request::route($request, $routes);

		$this->assertEquals($request['test_host_matched'], true);
		$this->assertEquals($request['test_https_matched'], true);
	}
}