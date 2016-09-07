<?php

namespace Drupal\govcms_ckan\Service;

use Drupal\govcms_ckan\Client\CkanClient;
use Drupal\govcms_ckan\Client\ClientInterface;
use Drupal\Core\Config\ConfigFactory;

class CkanService {

  private $client;

  const ACTION_DATASTORE_SEARCH = 'action/datastore_search';
  const ACTION_RESOURCE_SHOW = 'action/resource_show';
  const ACTION_TEST_HTTP = 'action/package_list';
  const ACTION_TEST_HTTPS = 'action/dashboard_activity_list';

  /**
   * [__construct description]
   * @param ConfigFactory $config [description]
   * @param [type]        $events [description]
   */
  public function __construct(ConfigFactory $ConfigFactory, $events) {
    $config = $ConfigFactory->get('govcms_ckan.settings');
    $client = new CkanClient($config->get('endpoint_url'), $config->get('api_key'));

    $this->client = $client;
    $this->events = $events;
  }

  /**
   * [getClient description]
   * @return [type] [description]
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * [setClient description]
   * @param ClientInterface $client [description]
   */
  public function setClient(ClientInterface $client) {
    $this->client = $client;
  }

  /**
   * [testConnect description]
   * @return [type] [description]
   */
  public function testConnection($url = FALSE) {
    if (!$url) {
      return $this->getClient()->get(SELF::ACTION_TEST_HTTP, ['limit' => 1]);
    }

    $uri = \Drupal::service('file_system')->uriScheme($url) == 'https'
      ? SELF::ACTION_TEST_HTTPS
      : SELF::ACTION_TEST_HTTP;

    // @TODO: Consider refactoring so we don't need to change the API url
    // directly here as this feels a bit awkward.
    return $this->getClient()->setApiUrl($url)->get($uri, ['limit' => 1]);
  }

  /**
   * [requestRecords description]
   * @param  [type] $resource_id [description]
   * @param  [type] $search      [description]
   * @param  [type] $filters     [description]
   * @return [type]              [description]
   */
  public function requestRecords($resource_id = NULL, $search = NULL, $filters = NULL) {
    $query = ['id' => $resource_id];

    if (!empty($search)) {
      $query += ['q' => $search];
    }

    if (!empty($filters)) {
      $query += ['filters' => $filters];
    }

    return $this->getClient()->get(SELF::ACTION_DATASTORE_SEARCH, $query);
  }

  /**
   * [requestMeta description]
   * @param  [type] $resource_id [description]
   * @return [type]              [description]
   */
  public function requestMeta($resource_id = NULL) {
    return $this->getClient()->get(SELF::ACTION_RESOURCE_SHOW, ['id' => $resource_id]);
  }
}
