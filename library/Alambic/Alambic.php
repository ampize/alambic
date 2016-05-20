<?php

namespace Alambic;

use GraphQL\GraphQL;
use \Exception;
use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class Alambic
{
    protected $alambicTypeDefs = [ ];

    protected $alambicTypes = [ ];

    protected $alambicQueryFields = [ ];

    protected $alambicMutationFields = [ ];

    protected $alambicConnectors = [ ];

    protected $sharedPipelineContext = [ ];

    protected $schema = null;

    public function __construct($config)
    {
        $this->initAlambicBaseTypes();
        if(!empty($config["alambicConnectors"])){
            $this->alambicConnectors=$config["alambicConnectors"];
        }
        if(!empty($config["alambicTypeDefs"])){
            $this->alambicTypeDefs=$config["alambicTypeDefs"];
        }
        $this->initSchema();
    }

    public function setSharedPipelineContext($newContext){
        $this->sharedPipelineContext=$newContext;
    }

    public function getSharedPipelineContext(){
        return $this->sharedPipelineContext;
    }

    public function execute($requestString=null,$variableValues=null,$operationName=null){

        try {
            $result = GraphQL::execute(
                $this->schema,
                $requestString,
                null,
                $variableValues,
                $operationName
            );
        } catch (Exception $exception) {
            $result = [
                'errors' => [
                    ['message' => $exception->getMessage()]
                ]
            ];
        }
        return $result;
    }

    protected function initAlambicBaseTypes(){
        $this->alambicTypes=[
            "String"=>Type::string(),
            "Int"=>Type::int(),
            "Float"=>Type::float(),
            "Boolean"=>Type::boolean(),
            "ID"=>Type::id(),
        ];
    }

    protected function loadAlambicType($typeName,$type){
        if (isset($this->alambicTypes[$typeName])){
            return;
        }
        $typeArray=[
            "name"=>$type["name"],
            "fields"=>[]
        ];
        foreach($type["fields"] as $fieldKey=>$fieldValue){
            $typeArray["fields"][$fieldKey]=$this->buildField($fieldKey,$fieldValue);
        }
        $this->alambicTypes[$typeName]=new ObjectType($typeArray);
        if(isset($type["expose"])&&$type["expose"]){
            if (!empty($type["singularEndpoint"])){
                $queryArray=[
                    "type"=>$this->alambicTypes[$typeName],
                ];
                if(!empty($type["singleQueryArgs"])&&is_array($type["singleQueryArgs"])){
                    $queryArray["args"]=[ ];
                    foreach($type["singleQueryArgs"] as $sargFieldKey=>$sargFieldValue){
                        $queryArray["args"][$sargFieldKey]=$this->buildField($sargFieldKey,$sargFieldValue);
                    }
                }
                if(!empty($type["connector"])&&is_array($type["connector"])){
                    $connectorConfig=$type["connector"]["configs"];
                    $connectorRef=$this->alambicConnectors[$type["connector"]["type"]];
                    $queryArray["resolve"]=function ($root, $args) use ($connectorRef,$connectorConfig){
                        return $connectorRef->resolve([
                            "configs"=>$connectorConfig,
                            "args"=>$args,
                            "multivalued"=>false
                        ]);
                    };
                }
                $this->alambicQueryFields[$type["singularEndpoint"]]=$queryArray;
            }
            if (!empty($type["multiEndpoint"])){
                $queryArray=[
                    "type"=>Type::listOf($this->alambicTypes[$typeName]),
                ];
                if(!empty($type["multiQueryArgs"])&&is_array($type["multiQueryArgs"])){
                    $queryArray["args"]=[ ];
                    foreach($type["multiQueryArgs"] as $margFieldKey=>$margFieldValue){
                        $queryArray["args"][$margFieldKey]=$this->buildField($margFieldKey,$margFieldValue);
                    }
                }
                if(!empty($type["connector"])&&is_array($type["connector"])){
                    $connectorConfig=$type["connector"]["configs"];
                    $connectorRef=$this->alambicConnectors[$type["connector"]["type"]];
                    $queryArray["resolve"]=function ($root, $args) use ($connectorRef,$connectorConfig){
                        return $connectorRef->resolve([
                            "configs"=>$connectorConfig,
                            "args"=>$args,
                            "multivalued"=>true
                        ]);
                    };
                }
                $this->alambicQueryFields[$type["multiEndpoint"]]=$queryArray;
            }
            if (!empty($type["mutations"])&&is_array($type["mutations"])){

                foreach($type["mutations"] as $mutationKey=>$mutationValue){
                    if (!isset($this->alambicTypes[$mutationValue["type"]])&&isset($this->alambicTypeDefs[$mutationValue["type"]])){
                        $this->loadAlambicType($mutationValue["type"],$this->alambicTypeDefs[$mutationValue["type"]]);
                    }
                    $mutationResultType=$this->alambicTypes[$mutationValue["type"]];
                    if(isset($mutationValue["multivalued"])&&$mutationValue["multivalued"]){
                        $mutationResultType=Type::listOf($mutationResultType);
                    }
                    if(isset($mutationValue["required"])&&$mutationValue["required"]){
                        $mutationResultType=Type::nonNull($mutationResultType);
                    }
                    $muationArray=[
                        "type"=>$mutationResultType,
                        "args"=>[]
                    ];
                    foreach($mutationValue["args"] as $mutargFieldKey=>$mutargFieldValue){
                        $muationArray["args"][$mutargFieldKey]=$this->buildField($mutargFieldKey,$mutargFieldValue);
                    }

                    if(!empty($type["connector"])&&is_array($type["connector"])){
                        $connectorConfig=$type["connector"]["configs"];
                        $connectorMethod=$mutationValue["methodName"];
                        $connectorRef=$this->alambicConnectors[$type["connector"]["type"]];
                        $muationArray["resolve"]=function ($root, $args) use ($connectorRef,$connectorConfig,$connectorMethod){
                            return $connectorRef->execute([
                                "configs"=>$connectorConfig,
                                "args"=>$args,
                                "methodName"=>$connectorMethod
                            ]);
                        };
                    }
                    $this->alambicMutationFields[$mutationKey]=$muationArray;

                }
            }
        }
    }

    protected function buildField($fieldKey,$fieldValue){
        if (!isset($this->alambicTypes[$fieldValue["type"]])&&isset($this->alambicTypeDefs[$fieldValue["type"]])){
            $this->loadAlambicType($fieldValue["type"],$this->alambicTypeDefs[$fieldValue["type"]]);
        }
        $baseTypeResult=$this->alambicTypes[$fieldValue["type"]];


        if(isset($fieldValue["multivalued"])&&$fieldValue["multivalued"]){
            $baseTypeResult=Type::listOf($baseTypeResult);
        }
        if(isset($fieldValue["required"])&&$fieldValue["required"]){
            $baseTypeResult=Type::nonNull($baseTypeResult);
        }
        $fieldResult=[
            "type"=>$baseTypeResult
        ];
        if(!empty($fieldValue["args"])&&is_array($fieldValue["args"])){
            $fieldResult["args"]=[ ];
            foreach($fieldValue["args"] as $eargFieldKey=>$eargFieldValue){
                $fieldResult["args"][$eargFieldKey]=$this->buildField($eargFieldKey,$eargFieldValue);
            }
        }
        if (isset($this->alambicTypeDefs[$fieldValue["type"]],$this->alambicTypeDefs[$fieldValue["type"]]["connector"])){
            $connectorConfig=$this->alambicTypeDefs[$fieldValue["type"]]["connector"]["configs"];
            $connectorRef=$this->alambicConnectors[$this->alambicTypeDefs[$fieldValue["type"]]["connector"]["type"]];
            $multivalued=isset($fieldValue["multivalued"])&&$fieldValue["multivalued"];
            $relation=isset($fieldValue["relation"])&&is_array($fieldValue["relation"]) ? $fieldValue["relation"] : [];
            $fieldResult["resolve"]=function ($obj,$args=[]) use ($connectorRef,$connectorConfig,$multivalued,$relation){
                foreach($relation as $relKey=>$relValue){
                    $args[$relKey]=$obj[$relValue];
                }
                return $connectorRef->resolve([
                    "configs"=>$connectorConfig,
                    "args"=>$args,
                    "multivalued"=>$multivalued
                ]);
            };

        }
        return $fieldResult;
    }
    protected function initSchema(){

        foreach($this->alambicTypeDefs as $key=>$value){
            $this->loadAlambicType($key,$value);
        }

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => $this->alambicQueryFields
        ]);

        $mutationType = new ObjectType([
            'name' => 'Mutation',
            'fields' => $this->alambicMutationFields
        ]);

        $this->schema = new Schema($queryType,$mutationType);
    }
}   