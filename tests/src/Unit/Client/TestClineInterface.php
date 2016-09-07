<?php

/**
 * @file
 * Test cases for Client Interface.
 */

namespace Drupal\govcms_ckan\Tests\Client;

use Drupal\Tests\UnitTestCase;
use Drupal\govcms_ckan\Client\ClientInterface;

class TestClientInterface extends UnitTestCase {

  public function setup() {
    $this->client = new Client('http://data.gov.au');
  }

  /**
   * Test the ability to fetch a resource.
   *
   * @group client
   */
  public function testFetch() {
    $response = $this->client->fetch();

    $this->assertClassHasAttribute('resposne', $this->client);
    $this->assertTrue($this->client->isValidResponse());
  }

  /**
   * Test the ability to build URLs for a resource.
   *
   * @group client
   */
  public function testUrl() {
    $url = $this->client->url(['test' => 'value'], 'test');
    
    $this->assertTrue(is_string($url));
    $this->assertEquals('http://data.gov.au/api/3/test?test=value', $url);
  }
}
