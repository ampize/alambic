<?php
namespace Alambic\Type;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Type\Definition\ScalarType;

class OmniArg  extends ScalarType
{

    public $name = 'OmniArg';

    public function serialize($value)
    {
        return $this->parseValue($value);
    }

    public function parseValue($value)
    {
        return $value;
    }

    public function parseLiteral($ast)
    {
        if($ast instanceof IntValueNode){
            return (int) $ast->value;
        } elseif ($ast instanceof FloatValueNode){
            return (float) $ast->value;
        } elseif ($ast instanceof BooleanValueNode){
            return (bool) $ast->value;
        } elseif ($ast instanceof StringValueNode){
            return (bool) $ast->value;
        }
        return null;
    }
}