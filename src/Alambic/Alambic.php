<?php

namespace Alambic;

use Alambic\Type\FilterValue;
use GraphQL\GraphQL;
use Exception;
use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use League\Pipeline\PipelineBuilder;
use Alambic\Exception\Config;
use GraphQL\Utils;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;

/**
 * Main Alambic class.
 *
 * Handle initialization, configuration and request processing
 *
 * @author Alexandru-Dobre
 */
class Alambic
{
    /**
     * Alambic type definitions.
     *
     * @var array[]
     */
    protected $alambicTypeDefs = [];

    /**
     * Alambic types.
     *
     * @var ObjectType[]
     */
    protected $alambicTypes = [];

    /**
     * Input Alambic types.
     *
     * @var InputObjectType[]
     */
    protected $inputAlambicTypes = [];

    /**
     * Alambic query fields.
     *
     * @var array[]
     */
    protected $alambicQueryFields = [];

    /**
     * Alambic mutation fields.
     *
     * @var array[]
     */
    protected $alambicMutationFields = [];

    /**
     * Alambic connector  config.
     *
     * @var array
     */
    protected $alambicConnectors = [];

    /**
     * Shared pipeline context.
     *
     * Is merged into pipeline params for all operations
     *
     * @var array
     */
    protected $sharedPipelineContext = [];

    /**
     * Pipeline cache.
     *
     * @var array
     */
    protected $pipelines = [];

    /**
     * Args to extract into pipeline params.
     *
     * @var string[]
     */
    protected $optionArgs = ['start', 'limit', 'orderBy', 'orderByDirection', 'filters'];

    /**
     * Scalar types to be automatically included in endpoint args.
     *
     * @var string[]
     */
    protected $autoArgScalars = ["String","Int","ID","Float","Boolean"];

    /**
     * GraphQL Schema.
     *
     * @var Schema
     */
    protected $schema = null;

    /**
     * Current main request string.
     *
     * @var String
     */
    protected $mainRequestString = "";

    /**
     * Input type for filters
     *
     * @var InputObjectType
     */
    protected $inputFiltersType = null;

    /**
     * Error messages for JSON decoding.
     *
     * @var array
     */
    protected $jsonErrorMessages = [
        JSON_ERROR_NONE => 'No error has occurred',
        JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
        JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
        JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
        JSON_ERROR_SYNTAX => 'Syntax error',
        JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    ];
    /**
     * Construct request-ready Alambic using config array.
     *
     * @param array $config
     * @param boolean $debug
     */
    public function __construct($config,$debug=false)
    {
        $this->initAlambicBaseTypes();
        $this->initInputFiltersType();
        if (is_string($config) && is_dir($config)) {
            $this->loadConfigFromFiles($config);
        } else {
            if (!empty($config['alambicConnectors'])) {
                $this->alambicConnectors = $config['alambicConnectors'];
            }
            if (!empty($config['alambicTypeDefs'])) {
                $this->alambicTypeDefs = $config['alambicTypeDefs'];
            }
            if (!empty($config['alambicTypes'])) {
                $this->alambicTypes=array_merge($this->alambicTypes,$config['alambicTypes']);
            }
            if (!empty($config['inputAlambicTypes'])) {
                $this->inputAlambicTypes=array_merge($this->inputAlambicTypes,$config['inputAlambicTypes']);
            }
            if (!empty($config['autoArgScalars'])) {
                $this->autoArgScalars=array_merge($this->autoArgScalars,$config['autoArgScalars']);
            }

        }
        $this->initSchema($debug);
    }

    /**
     * Init input type for filters.
     *
     */
    protected function initInputFiltersType(){
        $omniArg=new FilterValue();
        $this->inputFiltersType=new InputObjectType([
            'name' => 'AlambicFilters',
            'fields' => [
                'operator' => [
                    'type' => new EnumType([
                        'name' => 'AlambicFiltersOperator',
                        'values' => [
                            'and' => [
                                'value' => 'and',
                            ],
                            'or' => [
                                'value' => 'or',
                            ],

                        ]
                    ]),
                    "defaultValue"=>"and"
                ],
                'scalarFilters'=>[
                    'type'=>Type::listOf(new InputObjectType([
                        'name' => 'AlambicScalarFilter',
                        'fields' => [
                            'field' => [
                                'type' => Type::nonNull(Type::string()),
                            ],
                            'operator' => [
                                'type' => new EnumType([
                                    'name' => 'AlambicScalarOperator',
                                    'values' => [
                                        'eq' => [
                                            'value' => 'eq',
                                        ],
                                        'ne' => [
                                            'value' => 'ne',
                                        ],
                                        'lt' => [
                                            'value' => 'lt',
                                        ],
                                        'lte' => [
                                            'value' => 'lte',
                                        ],
                                        'gt' => [
                                            'value' => 'gt',
                                        ],
                                        'gte' => [
                                            'value' => 'gte',
                                        ],
                                        'like' => [
                                            'value' => 'like',
                                        ],
                                        'notLike' => [
                                            'value' => 'notLike',
                                        ],

                                    ]
                                ]),
                                "defaultValue"=>"eq"
                            ],
                            'value'=>[
                                'type'=>$omniArg,
                                "defaultValue"=>null
                            ]
                        ]
                    ]))
                ],
                'arrayFilters'=>[
                    'type'=>Type::listOf(new InputObjectType([
                        'name' => 'AlambicArrayFilter',
                        'fields' => [
                            'field' => [
                                'type' => Type::nonNull(Type::string()),
                            ],
                            'operator' => [
                                'type' => new EnumType([
                                    'name' => 'AlambicArrayOperator',
                                    'values' => [
                                        'in' => [
                                            'value' => 'in',
                                        ],
                                        'notIn' => [
                                            'value' => 'notIn',
                                        ],

                                    ]
                                ]),
                                "defaultValue"=>"in"
                            ],
                            'value'=>[
                                'type'=>Type::nonNull(Type::listOf($omniArg))
                            ]
                        ]
                    ]))
                ],
                'betweenFilters'=>[
                    'type'=>Type::listOf(new InputObjectType([
                        'name' => 'AlambicBetweenFilter',
                        'fields' => [
                            'field' => [
                                'type' => Type::nonNull(Type::string()),
                            ],
                            'operator' => [
                                'type' => new EnumType([
                                    'name' => 'AlambicBetweenOperator',
                                    'values' => [
                                        'between' => [
                                            'value' => 'between',
                                        ],
                                        'notBetween' => [
                                            'value' => 'notBetween',
                                        ],

                                    ]
                                ]),
                                "defaultValue"=>"between"
                            ],
                            'min'=>[
                                'type'=>Type::nonNull($omniArg)
                            ],
                            'max'=>[
                                'type'=>Type::nonNull($omniArg)
                            ]
                        ]
                    ]))
                ],

            ]
        ]);

    }
    /**
     * Set shared pipeline context.
     *
     * @param array $newContext
     */
    public function setSharedPipelineContext($newContext)
    {
        $this->sharedPipelineContext = $newContext;
    }

    /**
     * Get shared pipeline context.
     *
     * @return array
     */
    public function getSharedPipelineContext()
    {
        return $this->sharedPipelineContext;
    }

    /**
     * Process GraphQL request.
     *
     * @param $requestString
     * @param array <string, string>|null $variableValues
     * @param string|null                 $operationName
     *
     * @return array
     */
    public function execute($requestString = null, $variableValues = null, $operationName = null)
    {
        $this->mainRequestString=$requestString;
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
                    ['message' => $exception->getMessage()],
                ],
            ];
        }

        return $result;
    }

    /**
     * Load config from files in specified directory.
     *
     * @param string $path
     */
    protected function loadConfigFromFiles($path)
    {
        $connectorFiles = glob($path.'/connectors/*.json');
        foreach ($connectorFiles as $filePath) {
            $tempJson = file_get_contents($filePath);
            $jsonArray = json_decode($tempJson, true);
            if(!$jsonArray){
                throw new Config("JSON decode error in file ".$filePath." : ".$this->jsonErrorMessages[json_last_error()]);
            }
            $this->alambicConnectors = array_merge($this->alambicConnectors, $jsonArray);
        }
        $modelFiles = glob($path.'/models/*.json');
        foreach ($modelFiles as $filePath) {
            $tempJson = file_get_contents($filePath);
            $jsonArray = json_decode($tempJson, true);
            if(!$jsonArray){
                throw new Config("JSON decode error in file ".$filePath." : ".$this->jsonErrorMessages[json_last_error()]);
            }
            $this->alambicTypeDefs = array_merge($this->alambicTypeDefs, $jsonArray);
        }
    }

    /**
     * Initialize Alambic types using GraphQL scalar types.
     */
    protected function initAlambicBaseTypes()
    {
        $this->alambicTypes = [
            'String' => Type::string(),
            'Int' => Type::int(),
            'Float' => Type::float(),
            'Boolean' => Type::boolean(),
            'ID' => Type::id(),
        ];

        $this->inputAlambicTypes = [
            'String' => Type::string(),
            'Int' => Type::int(),
            'Float' => Type::float(),
            'Boolean' => Type::boolean(),
            'ID' => Type::id(),
        ];
    }

    /**
     * Create Alambic type using name and Alambic type definition. Also handles exposing query and mutation fields.
     *
     * @param string $typeName
     * @param array  $type
     */
    protected function loadAlambicType($typeName, $type)
    {
        if (isset($this->alambicTypes[$typeName])) {
            return;
        }
        if (isset($type['modelType']) && $type['modelType'] == 'Enum') {
            $typeArray = [
                'name' => $type['name'],
                'values' => $type['values'],
            ];
            if (!empty($type['description'])) {
                $typeArray['description'] = $type['description'];
            }
            $this->alambicTypes[$typeName] = new EnumType($typeArray);
        } elseif (isset($type['modelType']) && $type['modelType'] == 'Union') {
            $typeArray = [
                'name' => $type['name'],
                'types' => [ ]
            ];
            if (!empty($type['description'])) {
                $typeArray['description'] = $type['description'];
            }
            if(isset($type["types"])&&is_array($type["types"])){
                foreach($type["types"] as $unionMember){
                    if (!isset($this->alambicTypes[$unionMember]) && isset($this->alambicTypeDefs[$unionMember])) {
                        $this->loadAlambicType($unionMember, $this->alambicTypeDefs[$unionMember]);
                    }
                    $typeArray["types"][]=$this->alambicTypes[$unionMember];
                }
            }
            $typeArray['resolveType']=function ($value)  {
                if(isset($value["type"])){
                    return $this->alambicTypes[$value["type"]];
                }
            };

            $this->alambicTypes[$typeName] = new UnionType($typeArray);
        } else {
            $typeArray = [
                'name' => $type['name'],
                'fields'  => function() use ($type) {
                    $fields = [];
                    foreach ($type['fields'] as $fieldKey => $fieldValue) {
                        $fields[$fieldKey] = $this->buildField($fieldKey, $fieldValue,true);
                    }
                    return $fields;
                }
            ];
            if (!empty($type['description'])) {
                $typeArray['description'] = $type['description'];
            }
            $this->alambicTypes[$typeName] = new ObjectType($typeArray);
            if (isset($type['expose']) && $type['expose']) {
                if (!empty($type['singleEndpoint']) && is_array($type['singleEndpoint']) && !empty($type['singleEndpoint']['name'])) {
                    $queryArray = [
                        'type' => $this->alambicTypes[$typeName],
                    ];
                    $argOverrides=!empty($type['singleEndpoint']['args']) && is_array($type['singleEndpoint']['args']) ? $type['singleEndpoint']['args'] : [];
                    $excludedArgs=!empty($type['singleEndpoint']['excludedArgs']) && is_array($type['singleEndpoint']['excludedArgs']) ? $type['singleEndpoint']['excludedArgs'] : [];
                    $argsDefinition=$this->deduceEndpointArgs($type['fields'],$argOverrides,$excludedArgs,false);
                    if (count($argsDefinition)>0) {
                        $queryArray['args'] = [];
                        foreach ($argsDefinition as $sargFieldKey => $sargFieldValue) {
                            $queryArray['args'][$sargFieldKey] = $this->buildInputField($sargFieldKey, $sargFieldValue);
                        }
                    }
                    if (!empty($type['connector']) && is_array($type['connector'])) {
                        $connectorConfig = !empty($type['connector']['configs']) ? $type['connector']['configs'] : [];
                        $connectorType = $type['connector']['type'];
                        $connectorMethod = !empty($type['singleEndpoint']['methodName']) ? $type['singleEndpoint']['methodName'] : null;
                        $customPrePipeline = !empty($type['singleEndpoint']['prePipeline']) ? $type['singleEndpoint']['prePipeline'] : null;
                        $customPostPipeline = !empty($type['singleEndpoint']['postPipeline']) ? $type['singleEndpoint']['postPipeline'] : null;
                        $pipelineParams = !empty($type['singleEndpoint']['pipelineParams']) ? $type['singleEndpoint']['pipelineParams'] : [];
                        $pipelineParams["argsDefinition"]=$argsDefinition;
                        $queryArray['resolve'] = function ($root, $args) use ($connectorType, $connectorConfig, $connectorMethod, $customPrePipeline, $customPostPipeline, $pipelineParams, $typeName) {
                            $pipelineParams['parentRequestString'] = $this->mainRequestString;
                            return $this->runConnectorResolve($connectorType, [
                                'configs' => $connectorConfig,
                                'args' => $args,
                                'multivalued' => false,
                                'methodName' => $connectorMethod,
                                'pipelineParams' => $pipelineParams,
                            ], $customPrePipeline, $customPostPipeline, $typeName);
                        };
                    }
                    $this->alambicQueryFields[$type['singleEndpoint']['name']] = $queryArray;
                }
                if (!empty($type['multiEndpoint']) && is_array($type['multiEndpoint']) && !empty($type['multiEndpoint']['name'])) {
                    $queryArray = [
                        'type' => Type::listOf($this->alambicTypes[$typeName]),
                    ];
                    $argOverrides=!empty($type['multiEndpoint']['args']) && is_array($type['multiEndpoint']['args']) ? $type['multiEndpoint']['args'] : [];
                    $excludedArgs=!empty($type['multiEndpoint']['excludedArgs']) && is_array($type['multiEndpoint']['excludedArgs']) ? $type['multiEndpoint']['excludedArgs'] : [];
                    $argsDefinition=$this->deduceEndpointArgs($type['fields'],$argOverrides,$excludedArgs,false);

                    if (count($argsDefinition)>0) {
                        $queryArray['args'] = [];
                        foreach ($argsDefinition as $margFieldKey => $margFieldValue) {
                            $queryArray['args'][$margFieldKey] = $this->buildInputField($margFieldKey, $margFieldValue);
                        }
                    }
                    if (!empty($type['connector']) && is_array($type['connector'])) {
                        $connectorConfig = !empty($type['connector']['configs']) ? $type['connector']['configs'] : [];
                        $connectorType = $type['connector']['type'];
                        $connectorMethod = !empty($type['multiEndpoint']['methodName']) ? $type['multiEndpoint']['methodName'] : null;
                        $customPrePipeline = !empty($type['multiEndpoint']['prePipeline']) ? $type['multiEndpoint']['prePipeline'] : null;
                        $customPostPipeline = !empty($type['multiEndpoint']['postPipeline']) ? $type['multiEndpoint']['postPipeline'] : null;
                        $pipelineParams = !empty($type['multiEndpoint']['pipelineParams']) ? $type['multiEndpoint']['pipelineParams'] : [];
                        $pipelineParams["argsDefinition"]=$argsDefinition;
                        if (empty($queryArray['args'])) {
                            $queryArray['args'] = [];
                        }
                        $this->addOptionArgs($queryArray['args']);
                        $queryArray['resolve'] = function ($root, $args) use ($connectorType, $connectorConfig, $connectorMethod, $customPrePipeline, $customPostPipeline, $pipelineParams, $typeName) {
                            $pipelineParams['parentRequestString'] = $this->mainRequestString;
                            return $this->runConnectorResolve($connectorType, [
                                'configs' => $connectorConfig,
                                'args' => $args,
                                'multivalued' => true,
                                'methodName' => $connectorMethod,
                                'pipelineParams' => $pipelineParams,
                            ], $customPrePipeline, $customPostPipeline, $typeName);
                        };
                    }
                    $this->alambicQueryFields[$type['multiEndpoint']['name']] = $queryArray;
                }
                if (!empty($type['mutations']) && is_array($type['mutations'])) {
                    foreach ($type['mutations'] as $mutationKey => $mutationValue) {
                        if (!isset($this->alambicTypes[$mutationValue['type']]) && isset($this->alambicTypeDefs[$mutationValue['type']])) {
                            $this->loadAlambicType($mutationValue['type'], $this->alambicTypeDefs[$mutationValue['type']]);
                        }
                        $mutationResultType = $this->alambicTypes[$mutationValue['type']];
                        if (isset($mutationValue['multivalued']) && $mutationValue['multivalued']) {
                            $mutationResultType = Type::listOf($mutationResultType);
                        }
                        if (isset($mutationValue['required']) && $mutationValue['required']) {
                            $mutationResultType = Type::nonNull($mutationResultType);
                        }
                        $mutationArray = [
                            'type' => $mutationResultType,
                            'args' => [],
                        ];
                        $argOverrides=!empty($mutationValue['args']) && is_array($mutationValue['args']) ? $mutationValue['args'] : [];
                        $excludedArgs=!empty($mutationValue['excludedArgs']) && is_array($mutationValue['excludedArgs']) ? $mutationValue['excludedArgs'] : [];
                        $argsDefinition=$this->deduceEndpointArgs($type['fields'],$argOverrides,$excludedArgs,true);
                        foreach ($argsDefinition as $mutargFieldKey => $mutargFieldValue) {
                            $mutationArray['args'][$mutargFieldKey] = $this->buildInputField($mutargFieldKey, $mutargFieldValue);
                        }

                        if (!empty($type['connector']) && is_array($type['connector'])) {
                            $connectorConfig = !empty($type['connector']['configs']) ? $type['connector']['configs'] : [];
                            $connectorMethod = $mutationValue['methodName'];
                            $connectorType = $type['connector']['type'];
                            $customPrePipeline = !empty($mutationValue['prePipeline']) ? $mutationValue['prePipeline'] : null;
                            $customPostPipeline = !empty($mutationValue['postPipeline']) ? $mutationValue['postPipeline'] : null;
                            $pipelineParams = !empty($mutationValue['pipelineParams']) ? $mutationValue['pipelineParams'] : [];
                            $pipelineParams["argsDefinition"]=$argsDefinition;
                            $mutationArray['resolve'] = function ($root, $args) use ($connectorType, $connectorConfig, $connectorMethod, $customPrePipeline, $customPostPipeline, $pipelineParams, $typeName) {
                                return $this->runConnectorExecute($connectorType, [
                                    'configs' => $connectorConfig,
                                    'args' => $args,
                                    'methodName' => $connectorMethod,
                                    'pipelineParams' => $pipelineParams,
                                ], $customPrePipeline, $customPostPipeline, $typeName);
                            };
                        }
                        $this->alambicMutationFields[$mutationKey] = $mutationArray;
                    }
                }
            }
        }
    }


    /**
     * Create Alambic input type using name and Alambic type definition.
     *
     * @param string $typeName
     * @param array  $type
     */
    protected function loadInputAlambicType($typeName, $type)
    {
        if (isset($this->inputAlambicTypes[$typeName])) {
            return;
        }
        if (isset($type['modelType']) && $type['modelType'] == 'Enum') {
            $typeArray = [
                'name' => "Input_".$type['name'],
                'values' => $type['values'],
            ];
            if (!empty($type['description'])) {
                $typeArray['description'] = $type['description'];
            }
            $this->inputAlambicTypes[$typeName] = new EnumType($typeArray);
        } else {
            $typeArray = [
                'name' => "Input_".$type['name'],
                'fields' => [],
            ];
            if (!empty($type['description'])) {
                $typeArray['description'] = $type['description'];
            }
            foreach ($type['fields'] as $fieldKey => $fieldValue) {
                $typeArray['fields'][$fieldKey] = $this->buildInputField($fieldKey, $fieldValue);
            }
            $this->inputAlambicTypes[$typeName] = new InputObjectType($typeArray);


        }
    }

    /**
     * Deduce endpoint args from type field definition and endpoint properties
     *
     * @param array $typeArgs
     * @param array $argOverrides
     * @param array $excludedArgs
     * @param boolean  $isMutation
     *
     * @return array
     */
    protected function deduceEndpointArgs($typeArgs, $argOverrides=[], $excludedArgs=[], $isMutation=false)
    {
        $endpointArgs=[];
        foreach($typeArgs as $argKey=>$argValue){
            if(!$isMutation&&!in_array($argValue["type"],$this->autoArgScalars)&&(empty($this->alambicTypeDefs[$argValue["type"]]["modelType"])||$this->alambicTypeDefs[$argValue["type"]]["modelType"]!="Enum")){
                continue;
            }
            if(!empty($this->alambicTypeDefs[$argValue["type"]]["modelType"])&&$this->alambicTypeDefs[$argValue["type"]]["modelType"]=="Union"){
                continue;
            }
            if(isset($argOverrides[$argKey])){
                $endpointArgs[$argKey]=$argOverrides[$argKey];
            } elseif (!in_array("all",$excludedArgs)&&!in_array($argKey,$excludedArgs)&&!($isMutation&&isset($argValue["readOnly"])&&$argValue["readOnly"])){
                if(!$isMutation){
                    $argValue["required"]=false;
                }
                $endpointArgs[$argKey]=$argValue;
            }
        }
        $endpointArgs=array_merge($endpointArgs,$argOverrides);
        return $endpointArgs;
    }
    /**
     * Create valid GraphQL field using field key and definition array.
     *
     * @param string $fieldKey
     * @param array  $fieldValue
     * @param bool  $noNeedToCheck
     *
     * @return array
     */
    protected function buildField($fieldKey, $fieldValue, $noNeedToCheck=false)
    {
        if (!$noNeedToCheck&&!isset($this->alambicTypes[$fieldValue['type']]) && isset($this->alambicTypeDefs[$fieldValue['type']])) {
            $this->loadAlambicType($fieldValue['type'], $this->alambicTypeDefs[$fieldValue['type']]);
        }
        $baseTypeResult = $this->alambicTypes[$fieldValue['type']];

        if (isset($fieldValue['multivalued']) && $fieldValue['multivalued']) {
            $baseTypeResult = Type::listOf($baseTypeResult);
        }
        if (isset($fieldValue['required']) && $fieldValue['required']) {
            $baseTypeResult = Type::nonNull($baseTypeResult);
        }
        $fieldResult = [
            'type' => $baseTypeResult,
        ];
        if (!empty($fieldValue['description'])) {
            $fieldResult['description'] = $fieldValue['description'];
        }
        if (!empty($fieldValue['args']) && is_array($fieldValue['args'])) {
            $fieldResult['args'] = [];
            foreach ($fieldValue['args'] as $eargFieldKey => $eargFieldValue) {
                $fieldResult['args'][$eargFieldKey] = $this->buildField($eargFieldKey, $eargFieldValue,$noNeedToCheck);
            }
        }
        if (isset($this->alambicTypeDefs[$fieldValue['type']], $this->alambicTypeDefs[$fieldValue['type']]['connector'])) {
            $connectorConfig = !empty($this->alambicTypeDefs[$fieldValue['type']]['connector']['configs']) ? $this->alambicTypeDefs[$fieldValue['type']]['connector']['configs'] : [];;
            $connectorType = $this->alambicTypeDefs[$fieldValue['type']]['connector']['type'];
            $multivalued = isset($fieldValue['multivalued']) && $fieldValue['multivalued'];
            if ($multivalued) {
                if (empty($fieldResult['args'])) {
                    $fieldResult['args'] = [];
                }
                $this->addOptionArgs($fieldResult['args']);
            }
            $relation = isset($fieldValue['relation']) && is_array($fieldValue['relation']) ? $fieldValue['relation'] : [];
            $connectorMethod = !empty($fieldValue['methodName']) ? $fieldValue['methodName'] : null;
            $customPrePipeline = !empty($fieldValue['prePipeline']) ? $fieldValue['prePipeline'] : null;
            $customPostPipeline = !empty($fieldValue['postPipeline']) ? $fieldValue['postPipeline'] : null;
            $pipelineParams = !empty($fieldValue['pipelineParams']) ? $fieldValue['pipelineParams'] : [];
            $targetType = $fieldValue['type'];
            $fieldResult['resolve'] = function ($obj, $args = []) use ($connectorType, $connectorConfig, $multivalued, $relation, $connectorMethod, $customPrePipeline, $customPostPipeline, $pipelineParams, $targetType) {
                foreach ($relation as $relKey => $relValue) {
                    $args[$relKey] = $obj[$relValue];
                }
                if (isset($obj['currentRequestString'])) {
                    $pipelineParams['parentRequestString'] = $obj['currentRequestString'];
                }

                return $this->runConnectorResolve($connectorType, [
                    'configs' => $connectorConfig,
                    'args' => $args,
                    'multivalued' => $multivalued,
                    'methodName' => $connectorMethod,
                    'pipelineParams' => $pipelineParams,
                ], $customPrePipeline, $customPostPipeline, $targetType);
            };
        }

        return $fieldResult;
    }

    /**
     * Create valid GraphQL input field using field key and definition array.
     *
     * @param string $fieldKey
     * @param array  $fieldValue
     *
     * @return array
     */
    protected function buildInputField($fieldKey, $fieldValue)
    {
        if (!isset($this->inputAlambicTypes[$fieldValue['type']]) && isset($this->alambicTypeDefs[$fieldValue['type']])) {
            $this->loadInputAlambicType($fieldValue['type'], $this->alambicTypeDefs[$fieldValue['type']]);
        }
        $baseTypeResult = $this->inputAlambicTypes[$fieldValue['type']];

        if (isset($fieldValue['multivalued']) && $fieldValue['multivalued']) {
            $baseTypeResult = Type::listOf($baseTypeResult);
        }
        if (isset($fieldValue['required']) && $fieldValue['required']) {
            $baseTypeResult = Type::nonNull($baseTypeResult);
        }
        $fieldResult = [
            'type' => $baseTypeResult,
        ];
        if (!empty($fieldValue['description'])) {
            $fieldResult['description'] = $fieldValue['description'];
        }
        if (!empty($fieldValue['defaultValue'])) {
            $fieldResult['defaultValue'] = $fieldValue['defaultValue'];
        }

        return $fieldResult;
    }

    /**
     * Add option args to multivalued request fields.
     *
     * @param array $args
     */
    protected function addOptionArgs(&$args)
    {
        $args['start'] = [
            'type' => Type::int(),
        ];
        $args['limit'] = [
            'type' => Type::int(),
        ];
        $args['orderBy'] = [
            'type' => Type::string(),
        ];
        $args['orderByDirection'] = [
            'type' => Type::string(),
        ];
        $args['filters'] = [
            'type' => $this->inputFiltersType,
        ];
    }

    /**
     * Initialize GraphQL Schema.
     *
     * @param boolean $debug
     */
    protected function initSchema($debug=false)
    {
        foreach ($this->alambicTypeDefs as $key => $value) {
            if($debug){
                $this->loadAlambicType($key, $value);
            } else {
                try {
                    $this->loadAlambicType($key, $value);
                } catch (\Exception $e) {
                }
            }
        }

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => $this->alambicQueryFields,
        ]);

        $mutationType = new ObjectType([
            'name' => 'Mutation',
            'fields' => $this->alambicMutationFields,
        ]);

        $this->schema = new Schema([
            'query' => $queryType,
            'mutation' => $mutationType
        ]);
    }

    /**
     * Process read operation using connector pipeline.
     *
     * @param string     $connectorType
     * @param array      $payload
     * @param array|null $customPrePipeline
     * @param array|null $customPostPipeline
     * @param string     $targetType
     *
     * @return array
     */
    protected function runConnectorResolve($connectorType, $payload, $customPrePipeline = null, $customPostPipeline = null, $targetType = '')
    {
        $payload['isResolve'] = true;
        $payload['isMutation'] = false;
        $multivalued = isset($payload['multivalued']) ? $payload['multivalued'] : false;
        $currentRequestString = $targetType;
        if (!empty($payload['args'])) {
            $currentRequestString = $currentRequestString.'/'.json_encode($payload['args']);
        }
        if ($multivalued) {
            $currentRequestString = $currentRequestString.'/multivalued';
        }
        $payload['pipelineParams']['currentRequestString'] = $currentRequestString;
        if ($multivalued && !empty($payload['args'])) {
            foreach ($payload['args'] as $argKey => $argValue) {
                if (in_array($argKey, $this->optionArgs)) {
                    $payload['pipelineParams'][$argKey] = $argValue;
                    unset($payload['args'][$argKey]);
                }
            }
        }
        $result = $this->runConnectorPipeline($connectorType, $payload, $customPrePipeline, $customPostPipeline);
        if (!empty($result)) {
            if ($multivalued) {
                foreach ($result as &$dataItem) {
                    $dataItem['currentRequestString'] = $currentRequestString;
                }
            } else {
                $result['currentRequestString'] = $currentRequestString;
            }
        }

        return $result;
    }

    /**
     * Process write operation using connector pipeline.
     *
     * @param string     $connectorType
     * @param array      $payload
     * @param array|null $customPrePipeline
     * @param array|null $customPostPipeline
     *
     * @return array
     */
    protected function runConnectorExecute($connectorType, $payload, $customPrePipeline = null, $customPostPipeline = null, $targetType = '')
    {
        $payload['isResolve'] = false;
        $payload['isMutation'] = true;
        $payload['type'] = $targetType;
        $result = $this->runConnectorPipeline($connectorType, $payload, $customPrePipeline, $customPostPipeline);
        $result['type'] = $targetType;

        return $result;
    }

    /**
     * Process operations using connector pipeline. Handles pipeline building, cache and params.
     *
     * @param string     $connectorType
     * @param array      $payload
     * @param array|null $customPrePipeline
     * @param array|null $customPostPipeline
     *
     * @throws Exception
     *
     * @return array
     */
    protected function runConnectorPipeline($connectorType, $payload, $customPrePipeline = null, $customPostPipeline = null)
    {
        if (!isset($this->alambicConnectors[$connectorType])) {
            throw new Config('Undefined connector : '.$connectorType);
        }
        if (empty($payload['pipelineParams'])) {
            $payload['pipelineParams'] = [];
        }
        $payload['connectorBaseConfig'] = $this->alambicConnectors[$connectorType];
        $payload['pipelineParams'] = array_merge($payload['pipelineParams'], $this->sharedPipelineContext);
        $prePipeline = !empty($this->alambicConnectors[$connectorType]['prePipeline']) && is_array($this->alambicConnectors[$connectorType]['prePipeline']) ? $this->alambicConnectors[$connectorType]['prePipeline'] : [];
        $postPipeline = !empty($this->alambicConnectors[$connectorType]['postPipeline']) && is_array($this->alambicConnectors[$connectorType]['postPipeline']) ? $this->alambicConnectors[$connectorType]['postPipeline'] : [];
        if ($customPrePipeline && is_array($customPrePipeline)) {
            $prePipeline = $customPrePipeline;
        }
        if ($customPostPipeline && is_array($customPostPipeline)) {
            $postPipeline = $customPostPipeline;
        }
        $finalPipeline = array_merge($prePipeline, [$this->alambicConnectors[$connectorType]['connectorClass']], $postPipeline);
        $pipeLineKey = implode('-', $finalPipeline);
        if (!array_key_exists($pipeLineKey, $this->pipelines)) {
            $pipelineBuilder = (new PipelineBuilder());
            foreach ($finalPipeline as $stage) {
                $pipelineBuilder->add(new $stage());
            }
            $this->pipelines[$pipeLineKey] = $pipelineBuilder->build();
        }
        return $this->pipelines[$pipeLineKey]->process($payload)['response'];
    }

    /**
     * Check if value is valid using GraphQL Type
     *
     * @param array      $value
     * @param Type $type
     *
     * @return boolean
     */
    private function isValidValue($value, Type $type)
    {
        if ($type instanceof NonNull) {
            if (null === $value) {
                return false;
            }
            return $this->isValidValue($value, $type->getWrappedType());
        }
        if ($value === null) {
            return true;
        }
        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if (is_array($value)) {
                foreach ($value as $item) {
                    if (!$this->isValidValue($item, $itemType)) {
                        return false;
                    }
                }
                return true;
            } else {
                return $this->isValidValue($value, $itemType);
            }
        }
        if ($type instanceof InputObjectType) {
            if (!is_array($value)) {
                return false;
            }
            $fields = $type->getFields();
            $fieldMap = [];
            foreach ($fields as $fieldName => $field) {
                if (!$this->isValidValue(isset($value[$fieldName]) ? $value[$fieldName] : null, $field->getType())) {
                    return false;
                }
                $fieldMap[$field->name] = $field;
            }
            $diff = array_diff_key($value, $fieldMap);
            if (!empty($diff)) {
                return false;
            }
            return true;
        }
        Utils::invariant($type instanceof ScalarType || $type instanceof EnumType, 'Must be input type');
        return null !== $type->parseValue($value);
    }

    /**
     * Validates data against Alambic Type
     *
     * @param array $value
     * @param string $typeName
     *
     * @return boolean
     */
    public function validateData($value,$typeName){
        if (!isset($this->inputAlambicTypes[$typeName]) && isset($this->alambicTypeDefs[$typeName])) {
            $this->loadInputAlambicType($typeName, $this->alambicTypeDefs[$typeName]);
        }
        return $this->isValidValue($value,$this->inputAlambicTypes[$typeName]);
    }
}
