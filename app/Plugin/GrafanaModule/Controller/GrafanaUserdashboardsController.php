<?php
// Copyright (C) <2015>  <it-novum GmbH>
//
// This file is dual licensed
//
// 1.
//	This program is free software: you can redistribute it and/or modify
//	it under the terms of the GNU General Public License as published by
//	the Free Software Foundation, version 3 of the License.
//
//	This program is distributed in the hope that it will be useful,
//	but WITHOUT ANY WARRANTY; without even the implied warranty of
//	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//	GNU General Public License for more details.
//
//	You should have received a copy of the GNU General Public License
//	along with this program.  If not, see <http://www.gnu.org/licenses/>.
//

// 2.
//	If you purchased an openITCOCKPIT Enterprise Edition you can use this file
//	under the terms of the openITCOCKPIT Enterprise Edition license agreement.
//	License agreement and license key will be shipped with the order
//	confirmation.

use GuzzleHttp\Client;
use itnovum\openITCOCKPIT\Core\Views\Host;
use itnovum\openITCOCKPIT\Core\Views\Service;
use itnovum\openITCOCKPIT\Core\ServicestatusFields;
use itnovum\openITCOCKPIT\Grafana\GrafanaApiConfiguration;
use itnovum\openITCOCKPIT\Grafana\GrafanaPanel;
use itnovum\openITCOCKPIT\Grafana\GrafanaRow;
use itnovum\openITCOCKPIT\Grafana\GrafanaSeriesOverrides;
use itnovum\openITCOCKPIT\Grafana\GrafanaTag;
use itnovum\openITCOCKPIT\Grafana\GrafanaTarget;
use itnovum\openITCOCKPIT\Grafana\GrafanaTargetCollection;
use itnovum\openITCOCKPIT\Grafana\GrafanaTargetUnit;
use itnovum\openITCOCKPIT\Grafana\GrafanaTargetUnits;
use itnovum\openITCOCKPIT\Grafana\GrafanaThresholdCollection;
use itnovum\openITCOCKPIT\Grafana\GrafanaThresholds;
use itnovum\openITCOCKPIT\Grafana\GrafanaYAxes;
use Statusengine\PerfdataParser;

/**
 * Class GrafanaUserdashboardsController
 * @property GrafanaConfiguration $GrafanaConfiguration
 * @property GrafanaUserdashboard $GrafanaUserdashboard
 * @property GrafanaUserdashboardPanel $GrafanaUserdashboardPanel
 * @property GrafanaUserdashboardMetric $GrafanaUserdashboardMetric
 * @property \Host $Host
 * @property \Service $Service
 * @property Servicestatus $Servicestatus
 * @property Proxy $Proxy
 */
class GrafanaUserdashboardsController extends GrafanaModuleAppController {

    public $layout = 'angularjs';

    public $uses = [
        'GrafanaModule.GrafanaConfiguration',
        'GrafanaModule.GrafanaUserdashboard',
        'GrafanaModule.GrafanaUserdashboardPanel',
        'GrafanaModule.GrafanaUserdashboardMetric',
        'Host',
        'Service',
        MONITORING_SERVICESTATUS,
        'Proxy'
    ];

    public function index() {
        if (!$this->isApiRequest()) {
            //Only ship template for AngularJs
            return;
        }
        $allUserdashboards = $this->GrafanaUserdashboard->find('all', [
            // 'recursive' => -1,
            'conditions' => [
                'container_id' => $this->MY_RIGHTS
            ]
        ]);


        foreach ($allUserdashboards as $key => $dashboard) {
            $allUserdashboards[$key]['GrafanaUserdashboard']['allowEdit'] = false;
            if ($this->hasRootPrivileges == true) {
                $allUserdashboards[$key]['GrafanaUserdashboard']['allowEdit'] = true;
                continue;
            }
            foreach ($dashboard['Container'] as $cKey => $container) {
                if ($this->MY_RIGHTS_LEVEL[$container['id']] == WRITE_RIGHT) {
                    $allUserdashboards[$key]['GrafanaUserdashboard']['allowEdit'] = true;
                    continue;
                }
            }
        }

        $this->set('allUserdashboards', $allUserdashboards);
        $this->set('_serialize', ['allUserdashboards']);

    }

    public function add() {
        $grafanaConfig = $this->GrafanaConfiguration->find('first', [
            'recursive' => -1,
            'order'     => 'id DESC'
        ]);

        if (empty($grafanaConfig)) {
            //grafana is not yet configurated
        }

        if ($this->request->is('post')) {
            if (!isset($this->request->data['GrafanaUserdashboard']['configuration_id']) || empty($this->request->data['GrafanaUserdashboard']['configuration_id'])) {
                $this->request->data['GrafanaUserdashboard']['configuration_id'] = $grafanaConfig['GrafanaConfiguration']['id'];;
            }
            if ($this->GrafanaUserdashboard->saveAll($this->request->data)) {


                if ($this->isAngularJsRequest()) {
                    $this->setFlash(__('User defined Grafana dashboard created successfully.'));
                }

                if ($this->request->ext === 'json') {
                    $this->serializeId();
                }
                return;
            }
            $this->serializeErrorMessage();
        }
    }


    public function editor($userdashboardId = null) {
        if (!$this->request->is('GET')) {
            throw new MethodNotAllowedException();
        }

        if (!$this->GrafanaUserdashboard->exists($userdashboardId)) {
            throw new NotFoundException(__('Invalid Userdashboard'));
        }

        $dashboard = $this->GrafanaUserdashboard->find('first', $this->GrafanaUserdashboard->getQuery($userdashboardId));
        $dashboard['rows'] = $this->GrafanaUserdashboard->extractRowsWithPanelsAndMetricsFromFindResult($dashboard);

        $GrafanaUnits = new GrafanaTargetUnits();

        $this->set('userdashboardData', $dashboard);
        $this->set('grafanaUnits', $GrafanaUnits->getUnits());
        $this->set('_serialize', ['userdashboardData', 'grafanaUnits']);

        return;

    }

    public function getGrafanaUserdashboardUrl($userdashboardId) {

        $userdashboardData = $this->GrafanaUserdashboardData->find('all', [
            'recursive'  => -1,
            'conditions' => [
                'GrafanaUserdashboardData.userdashboard_id' => $userdashboardId
            ]
        ]);
        debug($userdashboardData);
        $userdashboardDataForGrafana = $this->GrafanaUserdashboardData->expandData($userdashboardData, true);
        debug($userdashboardDataForGrafana);

        $userdashboard = new \itnovum\openITCOCKPIT\Grafana\GrafanaUserdashboard();
        $userdashboard->setRows($userdashboardDataForGrafana);
        $userdashboard->setTitle('cooler title');
        $userdashboard->createUserdashboard();

        $this->set('userdashboardDataForGrafana', $userdashboardDataForGrafana);
        $this->set('_serialize', ['userdashboardDataForGrafana']);
    }


    public function view() {

    }

    public function delete() {

    }

    public function loadContainers() {
        if (!$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        if ($this->hasRootPrivileges === true) {
            $containers = $this->Tree->easyPath($this->MY_RIGHTS, CT_TENANT, [], $this->hasRootPrivileges);
        } else {
            $containers = $this->Tree->easyPath($this->getWriteContainers(), CT_TENANT, [], $this->hasRootPrivileges);
        }
        $containers = $this->Container->makeItJavaScriptAble($containers);


        $this->set('containers', $containers);
        $this->set('_serialize', ['containers']);
    }

    public function grafanaRow() {
        $this->layout = 'blank';
        return;
    }

    public function grafanaPanel() {
        $this->layout = 'blank';
        return;
    }

    public function getPerformanceDataMetrics($serviceId) {
        if (!$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        if (!$this->Service->exists($serviceId)) {
            throw new NotFoundException();
        }

        $service = $this->Service->find('first', [
            'recursive'  => -1,
            'fields'     => [
                'Service.id',
                'Service.uuid'
            ],
            'conditions' => [
                'Service.id' => $serviceId,
            ],
        ]);


        $ServicestatusFields = new ServicestatusFields($this->DbBackend);
        $ServicestatusFields->perfdata();
        $servicestatus = $this->Servicestatus->byUuid($service['Service']['uuid'], $ServicestatusFields);

        if (!empty($servicestatus)) {
            $PerfdataParser = new PerfdataParser($servicestatus['Servicestatus']['perfdata']);
            $this->set('perfdata', $PerfdataParser->parse());
            $this->set('_serialize', ['perfdata']);
            return;
        }
        $this->set('perfdata', []);
        $this->set('_serialize', ['perfdata']);
    }

    public function addMetricToPanel() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $service = $this->Service->find('first', [
            'recursive'  => -1,
            'fields'     => [
                'Service.id',
                'Service.host_id'
            ],
            'contain'    => [
                'Servicetemplate' => [
                    'fields' => [
                        'Servicetemplate.name'
                    ]
                ],
                'Host'            => [
                    'fields' => [
                        'Host.name'
                    ]
                ]
            ],
            'conditions' => [
                'Service.id' => $this->request->data('GrafanaUserdashboardMetric.service_id'),
            ],
        ]);

        if (empty($service)) {
            //Trigger validation error
            $this->request->data['GrafanaUserdashboardMetric']['service_id'] = null;
            $this->request->data['GrafanaUserdashboardMetric']['host_id'] = null;
        }

        if (!isset($this->request->data['GrafanaUserdashboardMetric'])) {
            throw new NotFoundException('Key GrafanaUserdashboardMetric not found in dataset');
        }

        $metric = $this->request->data;
        if (isset($service['Service']['host_id'])) {
            $metric['GrafanaUserdashboardMetric']['host_id'] = (int)$service['Service']['host_id'];
        }

        $this->GrafanaUserdashboardMetric->create();
        if ($this->GrafanaUserdashboardMetric->save($metric)) {
            $metric = $this->request->data['GrafanaUserdashboardMetric'];
            $metric['id'] = $this->GrafanaUserdashboardMetric->id;

            $host = new Host($service);
            $metric['Host'] = $host->toArray();

            $service = new Service($service);
            $metric['Service'] = $service->toArray();

            $this->set('metric', $metric);
            $this->set('_serialize', ['metric']);
            return;
        }
        $this->serializeErrorMessageFromModel('GrafanaUserdashboardMetric');
    }

    public function removeMetricFromPanel() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        if ($this->GrafanaUserdashboardMetric->exists($this->request->data('id'))) {
            $id = $this->request->data('id');
            if ($this->GrafanaUserdashboardMetric->delete($id)) {
                $this->set('success', true);
                $this->set('_serialize', ['success']);
                return;
            }
        }

        $this->set('success', false);
        $this->set('_serialize', ['success']);
    }

    public function addPanel() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $this->GrafanaUserdashboardPanel->create();
        if ($this->GrafanaUserdashboardPanel->save($this->request->data)) {
            $id = $this->GrafanaUserdashboardPanel->id;
            $this->set('panel', [
                'id'               => $id,
                'row'              => $this->request->data['GrafanaUserdashboardPanel']['row'],
                'userdashboard_id' => $this->request->data['GrafanaUserdashboardPanel']['userdashboard_id'],
                'unit'             => '',
                'metrics'          => []
            ]);
            $this->set('_serialize', ['panel']);
            return;
        }
        $this->serializeErrorMessageFromModel('GrafanaUserdashboardPanel');
    }

    public function removePanel() {
        if ($this->GrafanaUserdashboardPanel->exists($this->request->data('id'))) {
            $id = $this->request->data('id');
            if ($this->GrafanaUserdashboardPanel->delete($id)) {
                $this->set('success', true);
                $this->set('_serialize', ['success']);
                return;
            }
        }

        $this->set('success', false);
        $this->set('_serialize', ['success']);
    }

    public function addRow() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $id = $this->request->data('id');
        if (!$this->GrafanaUserdashboard->exists($id)) {
            throw new NotFoundException('GrafanaUserdashboard does not exisits');
        }

        $this->GrafanaUserdashboardPanel->create();
        $data = [
            'GrafanaUserdashboardPanel' => [
                'userdashboard_id' => $id,
                'row'              => $this->GrafanaUserdashboardPanel->getNextRow($id)
            ]
        ];
        if ($this->GrafanaUserdashboardPanel->save($data)) {
            $id = $this->GrafanaUserdashboardPanel->id;
            $this->set('success', true);
            $this->set('_serialize', ['success']);
            return;
        }
        $this->serializeErrorMessageFromModel('GrafanaUserdashboardPanel');
    }

    public function removeRow() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $ids = $this->request->data('ids');
        if (!empty($ids) && is_array($ids)) {
            $conditions = [
                'GrafanaUserdashboardPanel.id' => $ids
            ];
            if ($this->GrafanaUserdashboardPanel->deleteAll($conditions)) {
                $this->set('success', true);
                $this->set('_serialize', ['success']);
                return;
            }
        }

        $this->set('success', false);
        $this->set('_serialize', ['success']);
    }

    public function savePanelUnit() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $id = $this->request->data('id');
        $unit = $this->request->data('unit');
        $title = $this->request->data('title');

        $GrafanaTargetUnits = new GrafanaTargetUnits();
        if ($this->GrafanaUserdashboardPanel->exists($id) && $GrafanaTargetUnits->exists($unit)) {
            $panel = $this->GrafanaUserdashboardPanel->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'GrafanaUserdashboardPanel.id' => $id
                ],
            ]);

            $panel['GrafanaUserdashboardPanel']['unit'] = $unit;
            $panel['GrafanaUserdashboardPanel']['title'] = $title;
            if ($this->GrafanaUserdashboardPanel->save($panel)) {
                $this->set('success', true);
                $this->set('_serialize', ['success']);
                return;
            }
        }

        $this->set('success', false);
        $this->set('_serialize', ['success']);
    }

    public function synchronizeWithGrafana($id) {
        //$id = $this->request->data('id');
        if (!$this->GrafanaUserdashboard->exists($id)) {
            throw new NotFoundException();
        }

        $grafanaConfiguration = $this->GrafanaConfiguration->find('first', [
            'recursive' => -1,
            'contain'   => [
                'GrafanaConfigurationHostgroupMembership'
            ]
        ]);
        if (empty($grafanaConfiguration)) {
            throw new RuntimeException('No Grafana configuration found');
        }

        /** @var GrafanaApiConfiguration $GrafanaApiConfiguration */
        $GrafanaApiConfiguration = GrafanaApiConfiguration::fromArray($grafanaConfiguration);
        $client = $this->GrafanaConfiguration->testConnection($GrafanaApiConfiguration, $this->Proxy->getSettings());


        $dashboard = $this->GrafanaUserdashboard->find('first', $this->GrafanaUserdashboard->getQuery($id));
        $rows = $this->GrafanaUserdashboard->extractRowsWithPanelsAndMetricsFromFindResult($dashboard);

        if ($client instanceof Client) {
            $tag = new GrafanaTag();
            $GrafanaDashboard = new \itnovum\openITCOCKPIT\Grafana\GrafanaDashboard();
            $GrafanaDashboard->setTitle($dashboard['GrafanaUserdashboard']['name']);
            $GrafanaDashboard->setEditable(true);
            $GrafanaDashboard->setTags($tag->getTag());
            $GrafanaDashboard->setHideControls(false);

            foreach ($rows as $row) {
                $GrafanaRow = new GrafanaRow();
                foreach ($row as $panel) {
                    $GrafanaTargetCollection = new GrafanaTargetCollection();
                    $SpanSize = 12 / sizeof($row);
                    $GrafanaPanel = new GrafanaPanel($panel['id'], $SpanSize);
                    $GrafanaPanel->setTitle($panel['id'] . 'User entered panel title');

                    foreach ($panel['metrics'] as $metric) {
                        //@todo implement perfdata backends
                        $GrafanaTargetCollection->addTarget(
                            new GrafanaTarget(
                                sprintf(
                                    '%s.%s.%s.%s',
                                    $GrafanaApiConfiguration->getGraphitePrefix(),
                                    $metric['Host']['uuid'],
                                    $metric['Service']['uuid'],
                                    $metric['metric']
                                ),
                                new GrafanaTargetUnit($panel['unit'], true),
                                new GrafanaThresholds(null, null),
                                sprintf(
                                    '%s.%s.%s',
                                    $metric['Host']['hostname'],
                                    $metric['Service']['servicename'],
                                    $metric['metric']
                                )//Alias
                            ));
                    }
                    $GrafanaPanel->addTargets(
                        $GrafanaTargetCollection,
                        new GrafanaSeriesOverrides($GrafanaTargetCollection),
                        new GrafanaYAxes($GrafanaTargetCollection),
                        new GrafanaThresholdCollection($GrafanaTargetCollection)
                    );
                    $GrafanaRow->addPanel($GrafanaPanel);
                }
                $GrafanaDashboard->addRow($GrafanaRow);
            }
            $json = $GrafanaDashboard->getGrafanaDashboardJson();

            if ($json) {
                $request = new \GuzzleHttp\Psr7\Request('POST', $GrafanaApiConfiguration->getApiUrl() . '/dashboards/db', ['content-type' => 'application/json'], $json);
                try {
                    $response = $client->send($request);
                } catch (BadRequestException $e) {
                    $response = $e->getResponse();
                    $responseBody = $response->getBody()->getContents();
                    debug('<error>' . $responseBody . '</error>');
                }
                if ($response->getStatusCode() == 200) {
                    debug('<success>Dashboard created</success>');
                }
            }

        }

        debug($dashboard);

    }
}
