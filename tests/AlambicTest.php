<?php

class AlambicTest extends PHPUnit_Framework_TestCase
{
    public function testMethodUndefinedTata()
    {
        $this->expectException(Error::class);
        $alambic = new \Alambic\Alambic('schema');
        $alambic->tata();
    }

    public function testSimpleQuery()
    {
        $requestString = '{users{name posts {text}}}';

        $alambic = new \Alambic\Alambic('tests/schema');

        $result = $alambic->execute($requestString);

        $this->assertEquals('{"data":{"users":[{"name":"Luke","posts":[{"text":"May the Force be with you"}]},{"name":"Dark Vador","posts":[{"text":"May the Force be with you"}]}]}}', json_encode($result));
    }
}