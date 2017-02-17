<?php

namespace Alambic\Connector;



use Alambic\Exception\ConnectorArgs;
use Alambic\Exception\ConnectorConfig;
use Alambic\Exception\ConnectorInternal;

class Json
{
    public function __invoke($payload=[])
    {

        if (isset($payload["response"])) {
          return $payload;
        }
        $configs=isset($payload["configs"]) ? $payload["configs"] : [];
        $baseConfig=isset($payload["connectorBaseConfig"]) ? $payload["connectorBaseConfig"] : [];
        if(empty($configs["fullPath"])&&(empty($configs["fileName"])||empty($baseConfig["basePath"]))){
            throw new ConnectorConfig('Insufficient configuration : unable to resolve to a file path');
        }
        $filePath=!empty($configs["fullPath"]) ? $configs["fullPath"] : $baseConfig["basePath"].$configs["fileName"];
        if (!is_file($filePath)) {
           $jsonArray=[];
        } else {
            $tempJson = file_get_contents($filePath);
            $jsonArray=json_decode($tempJson,true);
        }
        return $payload["isMutation"] ? $this->execute($payload,$jsonArray,$filePath) : $this->resolve($payload,$jsonArray);

    }

    public function resolve($payload=[],$jsonArray){
        $multivalued=isset($payload["multivalued"]) ? $payload["multivalued"] : false;
        $args=isset($payload["args"]) ? $payload["args"] : [];
        $result=[];
        $start = !empty($payload['pipelineParams']['start']) ? $payload['pipelineParams']['start'] : 0;
        $limit = !empty($payload['pipelineParams']['limit']) ? $payload['pipelineParams']['limit'] : count($jsonArray);
        $sort=null;
        if (!empty($payload['pipelineParams']['orderBy'])) {
            $direction = !empty($payload['pipelineParams']['orderByDirection']) && ($payload['pipelineParams']['orderByDirection'] == 'desc') ? -1 : 1;
            $sort=$payload['pipelineParams']['orderBy'];
        }
        if($sort){
            usort($array, $this->build_sorter($sort,$direction));
        }
        $indexLimit=$start+$limit-1;
        $index=0;
        foreach($jsonArray as $record){
            $recordMatches=true;
            foreach($args as $argKey=>$argValue){
                if($recordMatches&&(!isset($record[$argKey])||$record[$argKey]!=$argValue)){
                    $recordMatches=false;
                }
            }
            if(($index>=$start)&&($index<=$indexLimit)){
                if ($recordMatches&&!$multivalued){
                    $payload["response"]=$record;
                    return $payload;
                } elseif($recordMatches&&$multivalued){
                    $result[]=$record;
                }
            }
            if($recordMatches){
                $index=$index+1;
            }
        }
        $payload["response"]=$result;
        return $payload;
    }

    public function execute($payload=[],$jsonArray,$filePath){
        $args=isset($payload["args"]) ? $payload["args"] : [];
        $methodName=isset($payload["methodName"]) ? $payload["methodName"] : null;
        if(empty($methodName)){
            throw new ConnectorConfig('Json connector requires a valid methodName for write ops');
        }
        if(empty($args["id"])){
            throw new ConnectorArgs('Json connector id for write ops');
        }
        $result=[];
        if($methodName=="create"){
            $jsonArray[]=$args;
            $result=$args;
        } else {
            $recordFound = false;
            foreach ($jsonArray as $recordKey => $record) {
                if (!$recordFound && isset($record["id"]) && $record["id"] == $args["id"]) {
                    if ($methodName == "update") {
                        foreach ($args as $argKey => $argValue) {
                            $record[$argKey] = $argValue;
                        }
                        $result = $record;
                        $jsonArray[$recordKey] = $record;

                    } elseif ($methodName == "delete") {
                        unset($jsonArray[$recordKey]);
                    }
                    $recordFound = true;
                }
            }
        }
        file_put_contents($filePath,json_encode($jsonArray));
        $payload["response"]=$result;
        return $payload;
    }

    protected function build_sorter($key,$order) {
        return function ($a, $b) use ($key,$order) {
            if(!isset($a[$key])&&!isset($b[$key])){
                return 0;
            } elseif (!isset($a[$key])){
                return -1*$order;
            } elseif (!isset($b[$key])){
                return $order;
            } elseif ($a[$key] == $b[$key]){
                return 0;
            } elseif (is_string($a[$key])&&is_string($b[$key])) {
                return $order*strnatcmp($a[$key], $b[$key]);
            } else {
                $res=($a[$key] < $b[$key]) ? -1 : 1;
                return $res*$order;
            }

        };
    }

}
