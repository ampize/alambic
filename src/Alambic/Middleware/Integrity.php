<?php

namespace Alambic\Middleware;



use \Exception;

class Integrity
{
    public function __invoke($payload=[])
    {
        if(!$payload["isMutation"]||isset($payload["response"])||empty($payload["pipelineParams"]["argsDefinition"])||!is_array($payload["pipelineParams"]["argsDefinition"])){
            return $payload;
        }
        if(!isset($payload["args"])){
            $payload["args"]=[];
        }
        foreach($payload["pipelineParams"]["argsDefinition"] as $argDefKey=>$argDefValue){
            //check field integrity
        }
        return $payload;
    }


}
