<?php declare(strict_types=1); namespace Andromeda\Core; require_once("init.php");

class TestClass1 { public function __toString() : string { return "TestClass1..."; } }
class TestClass2 { }
class TestClass3 { }

class UtilitiesTest extends \PHPUnit\Framework\TestCase
{
    public function testJSON() : void
    {
        $this->assertSame('{"test":55}',Utilities::JSONEncode(array('test'=>55)));
        $this->assertSame(array('test'=>55),Utilities::JSONDecode('{"test":55}'));
    }
    
    public function testBadJSONEncode() : void
    {
        $this->expectException(Exceptions\JSONException::class);
        Utilities::JSONEncode(array(Crypto::GenerateSecretKey()));
    }
    
    public function testBadJSONDecode() : void
    {
        $this->expectException(Exceptions\JSONException::class);
        Utilities::JSONDecode("nothing here!");
    }
    
    public function testIsUTF8() : void
    {
        $this->assertTrue(Utilities::isUTF8(""));
        $this->assertTrue(Utilities::isUTF8("test"));
        $this->assertTrue(Utilities::isUTF8("\u{9999}"));
        $this->assertTrue(Utilities::isUTF8("😜"));
        
        $this->assertFalse(Utilities::isUTF8(strval(hex2bin("deadbeef"))));
    }
    
    public function testArrayLast() : void
    {
        $this->assertSame(Utilities::array_last(array()), null);
        $this->assertSame(Utilities::array_last(array(5)), 5);
        $this->assertSame(Utilities::array_last(array(1,2,3)), 3);
        $this->assertSame(Utilities::array_last(array(4=>'test',7=>'test2',5=>'test3')), 'test3');
        $this->assertSame(Utilities::array_last(array('b'=>5,'a'=>4)), 4);
    }
    
    public function testShortClassName() : void
    {
        $this->assertSame("Utilities",Utilities::ShortClassName(Utilities::class));
    }
    
    public function testFirstUpper() : void
    {
        $this->assertSame("", Utilities::FirstUpper(""));
        $this->assertSame("R", Utilities::FirstUpper("r"));
        $this->assertSame("R", Utilities::FirstUpper("R"));
        $this->assertSame("ArAaBbC😜", Utilities::FirstUpper("arAaBbC😜"));
    }

    public function testCapitalizeWords() : void
    {
        $this->assertSame("", Utilities::CapitalizeWords(""));
        $this->assertSame("My Name😜 123", Utilities::CapitalizeWords("  my name😜 123 "));
    }
    
    public function testReturnBytes() : void
    {
        $this->assertSame(Utilities::return_bytes(""), 0);
        
        $this->assertSame(Utilities::return_bytes("0"), 0);
        $this->assertSame(Utilities::return_bytes("75"), 75);
        
        $this->assertSame(Utilities::return_bytes("0B"), 0);
        $this->assertSame(Utilities::return_bytes("0 B"), 0);
        
        $this->assertSame(Utilities::return_bytes("27"), 27);
        
        $this->assertSame(Utilities::return_bytes("1K"), 1024);
        $this->assertSame(Utilities::return_bytes("1 K"), 1024);
        
        $this->assertSame(Utilities::return_bytes("10G"), 10*1024*1024*1024);
        $this->assertSame(Utilities::return_bytes("10 G"), 10*1024*1024*1024);
    }
    
    public function testReplaceFirst() : void
    {
        $this->assertSame(Utilities::replace_first("","",""), "");
        $this->assertSame(Utilities::replace_first("test","test2",""), "");
        $this->assertSame(Utilities::replace_first("test","test2","test"), "test2");
        $this->assertSame(Utilities::replace_first("test","test2","3testtest5😜"), "3test2test5😜");      
    }

    public function testEscapeAll() : void
    {
        $this->assertSame("", Utilities::escape_all("",['a']));
        $this->assertSame("\\a", Utilities::escape_all("a",['a']));
        $this->assertSame("\\\\", Utilities::escape_all("\\",['b']));

        // test _ __ ___ 2  ->  test \_ \_\_ \_\_\_ 2
        $this->assertSame("test \\_ \\_\\_ \\_\\_\\_ 2", Utilities::escape_all("test _ __ ___ 2",['_']));

        // test __\__ 2  ->  test \_\_\\\_\_ 2
        $this->assertSame("test \\_\\_\\\\\\_\\_ 2", Utilities::escape_all("test __\\__ 2",['_']));

        // test \_ \\_ \\\_ \\\\_ _%\%_ 2 -> test \\\_ \\\\\_ \\\\\\\_ \\\\\\\\\_ \_\%\\\%\_ 2
        $this->assertSame("test \\\\\\_ \\\\\\\\\\_ \\\\\\\\\\\\\\_ \\\\\\\\\\\\\\\\\\_ \\_\\_\\\\\\_\\_ 2", 
            Utilities::escape_all("test \\_ \\\\_ \\\\\\_ \\\\\\\\_ __\\__ 2",['_','%']));

        // 90 should not get escaped as this is part of a UTF-8 character
        $this->assertTrue(Utilities::isUTF8($str="\xe1\x8b\x90"));
        //$this->assertSame(bin2hex("$str"),bin2hex(Utilities::escape_all("$str",["\x90"])));
    }
    
    public function testArrayMapKeys() : void
    {
        $func = function(string $p){ return $p.'5'; };
        
        $this->assertSame(Utilities::array_map_keys($func, array()), array());
        
        $this->assertSame(Utilities::array_map_keys($func, 
            array('a','b','c')), array('a'=>'a5','b'=>'b5','c'=>'c5'));
    }
    
    public function testIsScalarArray() : void
    {
        $this->assertTrue(Utilities::is_plain_array(array()));
        $this->assertTrue(Utilities::is_plain_array(array(0=>false)));
        $this->assertTrue(Utilities::is_plain_array(array(0=>0,1=>'a',2=>3.14)));
        $this->assertTrue(Utilities::is_plain_array([1,2,3,4,5]));
        $this->assertTrue(Utilities::is_plain_array(['a','b','c']));
        
        $this->assertFalse(Utilities::is_plain_array(array(1=>1)));
        $this->assertFalse(Utilities::is_plain_array(array(0=>1,2=>1)));
        $this->assertFalse(Utilities::is_plain_array(array(0=>1,'test'=>false)));
        $this->assertFalse(Utilities::is_plain_array(array(1.0=>3)));
        $this->assertFalse(Utilities::is_plain_array(array('test'=>5)));
        $this->assertFalse(Utilities::is_plain_array(array(0=>5,1=>array(1,2))));
    }
    
    public function testCaptureOutput() : void
    {
        $this->assertSame(Utilities::CaptureOutput(function(){ }), "");
        $this->assertSame(Utilities::CaptureOutput(function(){ echo "test"; }), "test");       
    }

    public function testArrayStrings() : void
    {
        $in = array(
            'a' => 5,
            'b' => 'mytest',
            'c' => array(
                'c0' => 6,
                'c1' => array(
                    'test😜',
                    new TestClass3(),
                    hex2bin("deadbeef") // not UTF-8
                ),
                'c2' => new TestClass2()
            ),
            'd' => new TestClass1()
        );
        
        $out = array(
            'a' => 5,
            'b' => 'mytest',
            'c' => array(
                'c0' => 6,
                'c1' => array(
                    'test😜',
                    'Andromeda\Core\TestClass3',
                    '3q2+7w==' // base64
                ),
                'c2' => 'Andromeda\Core\TestClass2'
            ),
            'd' => 'TestClass1...'
        );
        
        $this->assertSame($out, Utilities::toScalarArray($in));
    }
}
