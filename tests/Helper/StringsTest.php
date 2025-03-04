<?php

namespace Test\Helper;

use KerrialNewham\Migrator\Helper\Strings;
use PHPUnit\Framework\TestCase;

class StringsTest extends TestCase
{
    public function testToCamelCase()
    {
        $this->assertEquals('helloWorld', Strings::toCamelCase('hello_world'));
        $this->assertEquals('helloWorld', Strings::toCamelCase('hello-world'));
        $this->assertEquals('helloWorld', Strings::toCamelCase('Hello World'));
        $this->assertEquals('helloWorld', Strings::toCamelCase('hello world'));
        $this->assertEquals('helloWorld', Strings::toCamelCase('HELLO_WORLD'));
        $this->assertEquals('helloWorld', Strings::toCamelCase('HELLO-WORLD'));
        $this->assertEquals('hello', Strings::toCamelCase('hello'));
        $this->assertEquals('h3lloWorld', Strings::toCamelCase('h3llo-world'));
        $this->assertEquals('helloWorld123', Strings::toCamelCase('hello-world-123'));
        $this->assertEquals('helloWorld', Strings::toCamelCase('hello---world'));
        $this->assertEquals('helloWorld', Strings::toCamelCase('hello___world'));
        $this->assertEquals('HelloWorld', ucfirst(Strings::toCamelCase('hello-world')));
    }
}

