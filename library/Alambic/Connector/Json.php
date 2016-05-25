<?php

namespace Alambic\Connector;



use \Exception;

class Json
{
    public function __invoke($payload=[])
    {
        $configs=isset($payload["configs"]) ? $payload["configs"] : [];
        $baseConfig=isset($payload["connectorBaseConfig"]) ? $payload["connectorBaseConfig"] : [];
        if(empty($configs["fullPath"])&&(empty($configs["fileName"])||empty($baseConfig["basePath"]))){
            throw new Exception('Insufficient configuration : unable to resolve to a file path');
        }
        $filePath=!empty($configs["fullPath"]) ? $configs["fullPath"] : $baseConfig["basePath"].$configs["fileName"];
        if (!is_file($filePath)) {
            throw new Exception('File not found');
        }
        $tempJson = file_get_contents($filePath);
        $jsonArray=json_decode($tempJson,true);
        return $payload["isMutation"] ? $this->execute($payload,$jsonArray) : $this->resolve($payload,$jsonArray);

    }

    public function resolve($payload=[],$jsonArray){
        $multivalued=isset($payload["multivalued"]) ? $payload["multivalued"] : false;
        $args=isset($payload["args"]) ? $payload["args"] : [];
        $result=[];
        foreach($jsonArray as $record){
            $recordMatches=true;
            foreach($args as $argKey=>$argValue){
                if($recordMatches&&(!isset($record[$argKey])||$record[$argKey]!=$argValue)){
                    $recordMatches=false;
                }
            }
            if ($recordMatches&&!$multivalued){
                return $record;
            } elseif($recordMatches&&$multivalued){
                $result[]=$record;
            }
        }
        return $result;
    }

    public function execute($payload=[],$jsonArray){
        //WIP
        return [];
    }

}
