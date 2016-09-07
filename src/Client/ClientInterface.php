<?php

/**
 * @file
 * Contains \Drupal\govcms_ckan\Client\ClientInterface.
 *
 * Response Object.
 * ----------------
 * A standardised format for the object returned. It is cached locally and
 * contains the following properties:
 * - valid: (bool) If the API request was a success or failure.
 * - request_time (int) The time of the request to the API.
 * - code: (int) Response code from the request to the API http request.
 * - status: (string) Status message from the API http request.
 * - resource: (string) The requested resource.
 * - query: (array) The query params passed to the resource.
 * - data: (mixed) An object or array response from the API request.
 */

namespace Drupal\govcms_ckan\Client;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;

abstract class ClientInterface {

  use StringTranslationTrait;
  use UseCacheBackendTrait;

  /**
   * API variables.
   */
  protected $apiUrl;
  protected $apiKey;
  protected $apiPath = '/api/%d/';
  protected $apiVersion = 3;

  /**
   * Request variables.
   */
  protected $resource;
  protected $query;
  protected $url;

  /**
   * Response variable, gets updated whenever get/fetch is called.
   */
  protected $response;

  /**
   * Response object, contains the object to be returned.
   */
  protected $responseObject;

  /**
   * How long to cache for if request was successful (in seconds).
   */
  protected $cacheExpirySuccess = 2592000;

  /**
   * How long to cache for if request failed (in seconds).
   */
  protected $cacheExpiryFail = 86400;

  /**
   * GovCmsCkanClient constructor.
   *
   * @param string $base_url
   *   The API base url for this endpoint.
   * @param string $api_key
   *   The API key for this endpoint.
   * @param int $api_version
   *   The version of the API to use.
   */
  public function __construct($base_url, $api_key = NULL, $api_version = 3) {
    $this->apiUrl = $base_url;
    $this->apiKey = $api_key;
    $this->apiVersion = $api_version;
    $this->cacheBackend = \Drupal::cache();
  }

  public function setApiUrl($url = '') {
    $this->apiUrl = $url;
    return $this;
  }

  public function setApiKey($key = '') {
    $this->apiKey = $key;
    return $this;
  }

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
  abstract public function testConnection($resource, array $query = []);

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
  abstract public function get($resource, array $query = []);

  /**
   * Check the response is OK and valid.
   */
  protected function isValidResponse() {
    var_dump($this->response);
    exit;
    return $this->response->getStatusCode() == 200;
  }

  /**
   * Get the API path with the correct api version number.
   *
   * @return string
   *   The API path with version number included. Eg. '/api/3/'.
   */
  protected function apiPath() {
    return \sprintf($this->apiPath, $this->apiVersion);
  }

  protected function getCID() {
    return 'govcms_ckan.' . \sha1($this->url);
  }

  /**
   * Populate the responseObject with the cached version if exists.
   *
   * @return bool
   *   TRUE if successful cache get, FALSE if not.
   */
  protected function cacheDataGet() {
    $cache = $this->cacheGet($this->getCID());

    if (!empty($cache->data) && $cache->expire > time()) {
      $this->response = $cache->data;
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Save current responseObject to Drupal cache.
   *
   * We use the url for the cache key as that will be unique to the request.
   */
  protected function cacheDataSet() {
    // @TODO: Check response for validity to increase time().
    $this->cacheSet($this->getCID(), $this->response, time() + 3600);
  }

  /**
   * Fetch CKAN data.
   */
  protected function fetch($url = NULL, $cache = TRUE) {
    $url = empty($url) ? $this->url() : $url;

    // If no cache, we do a new request, parse and cache it.
    // If cache was present, the responseObject will be updated.
    if ($this->cacheDataGet() === FALSE) {
      $client = \Drupal::httpClient();
      $this->response = $client->request('GET', $url, [
        'headers' => [
          'Authorization' => $this->apiKey,
        ],
      ]);

      // Parse the response.
      // $this->parseData();

      if ($cache) {
        $this->cacheDataSet();
      }
    }

    return $this->response;
  }

  /**
   * Parse the raw CKAN data into a standardised object.
   *
   * @depricated: In favour of Guzzle response object.
   */
  protected function parseData() {
    // Build a generic response object.
    $this->responseObject = (object) array(
      'valid' => $this->isValidResponse(),
      'request_time' => time(),
      'code' => $this->response->code,
      'status' => $this->response->status_message,
      'url' => $this->url,
      'resource' => $this->resource,
      'query' => $this->query,
      'data' => (object) array(),
    );

    // Data only gets populated if we have a valid response.
    if ($this->responseObject->valid && isset($this->response->data)) {
      // TODO: Autodetect response format and handle errors if not JSON?
      $data = Json::decode($this->response->data);
      $this->responseObject->data = $data->result;
      // There is a possibility that we get a 200 code but failed request.
      // @see http://docs.ckan.org/en/latest/api/#making-an-api-request
      $this->responseObject->valid = $data->success;
    }

    if (!$this->responseObject->valid) {
      $this->errorLogger();
    }
  }

  /**
   * Handle errors.
   *
   * @TODO: Add logging around @self::fetch.
   */
  protected function errorLogger($url) {
    // Log to watchdog.
    $message = $this->t('Error requesting data from CKAN endpont: @url - Error @code - @status', [
      '@url' => $url,
    ]);

    \Drupal::logger('govcms_ckan_client')->error($message);
  }

  /**
   * Standard way of generating URLs for the client.
   *
   * @param array $query
   *   An array of query params.
   *
   * @return string
   *   A formated URL.
   */
  protected function url(array $query = [], $resource = '') {
    try {
      $url = Url::fromUri(
        $this->apiUrl . $this->apiPath() . $resource,
        ['query' => $query]
      );
    } catch (Exception $error) {
      $this->errorLogger($this->apiUrl . $this->apiPath() . $resource);
      return FALSE;
    }

    return $url->toString();
  }
}
