<?php

namespace Alambic\Connector;

use Alambic\Exception\ConnectorArgs;
use Alambic\Exception\ConnectorConfig;
use Alambic\Exception\ConnectorInternal;

/**
 * Class AbstractConnector
 */
abstract class AbstractConnector
{
    protected $payload;
    protected $config;
    protected $args;
    protected $multivalued;
    protected $start = 0;
    protected $limit = 10;
    protected $orderBy = null;
    protected $orderByDirection = 'DESC';
    protected $argsDefinition;
    protected $requiredConfig = [];
    protected $requiredArgs = [];
    protected $connection;

    abstract public function __invoke($payload=[]);

    protected function setPayload($payload) {
         $this->payload = $payload;
         $configs = isset($payload["configs"]) ? $payload["configs"] : [];
         $baseConfig=isset($payload["connectorBaseConfig"]) ? $payload["connectorBaseConfig"] : [];
         $this->config = array_merge($baseConfig, $configs);
         $this->args=isset($this->payload["args"]) ? $payload["args"] : [];
         $this->multivalued=isset($payload["multivalued"]) ? $payload["multivalued"] : false;
         if (!empty($payload['pipelineParams']['start'])) $this->start = $payload['pipelineParams']['start'];
         if (!empty($payload['pipelineParams']['limit'])) $this->limit = $payload['pipelineParams']['limit'];
         if (!empty($payload['pipelineParams']['orderBy'])) $this->orderBy = $payload['pipelineParams']['orderBy'];
         if (!empty($payload['pipelineParams']['orderByDirection'])) $this->orderByDirection = $payload['pipelineParams']['orderByDirection'];
         if (!empty($payload['pipelineParams']['argsDefinition'])) $this->argsDefinition = $payload['pipelineParams']['argsDefinition'];
     }

    protected function checkConfig() {
        foreach($this->requiredConfig as $var => $msg) {
            if (empty($this->config[$var])) {
                throw new ConnectorConfig($msg);
            }
        }
    }

}
