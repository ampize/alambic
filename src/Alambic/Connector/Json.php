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
        return $payload["isMutation"] ? $this->execute($payload,$jsonArray,$filePath) : $this->resolve($payload,$jsonArray);

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
                $payload["response"]=$record;
                return $payload;
            } elseif($recordMatches&&$multivalued){
                $result[]=$record;
            }
        }
        $payload["response"]=$result;
        return $payload;
    }

    public function execute($payload=[],$jsonArray,$filePath){
        $args=isset($payload["args"]) ? $payload["args"] : [];
        $methodName=isset($payload["methodName"]) ? $payload["methodName"] : null;
        if(empty($methodName)){
            throw new Exception('Json connector requires a valid methodName for write ops');
        }
        if(empty($args["id"])){
            throw new Exception('Json connector id for write ops');
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

}
