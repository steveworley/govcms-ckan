<?php
/**
 * @file
 * GovCmsCkanClient Class for fetching, caching and returning CKAN data.
 *
 * Basic Example Usage.
 * --------------------
 * $client = new \Drupal\govcms_ckan\Client\CkanClient($base_url, $api_key);
 * $client->get('action/package_show', array('id' => 'fd49dc83f86f'));
 */

namespace Drupal\govcms_ckan\Client;

/**
 * Defines the GovCMS CKAN Client class.
 */
class CkanClient extends ClientInterface {

  /**
   * Test an endpoint is functional.
   *
   * @param string $resource
   *   The resource we are requesting.
   * @param array $query
   *   A key value pair of url paramaters.
   *
   * @return int
   *   The response code from the resource, 200 is success.
   */
  public function testConnection($resource, array $query = []) {
    $test_url = $this->url($query, $resource);
    $this->fetch($test_url, FALSE);

    return $this->isValidResponse();
  }

  /**
   * Return the data.
   *
   * @param string $resource
   *   Resource path.
   * @param array $query
   *   A key pair array forming the url paramaters.
   *
   * @return mixed
   *   Parsed response.
   */
  public function get($resource, array $query = []) {
    // Prepare the variables for the url.
    $url = $this->url($query, $resource);
    
    // Fetch the response.
    return $this->fetch($url);
  }
}
