<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; require_once("init.php");

use Andromeda\Core\{Crypto,Utilities};

/** @phpstan-import-type ScalarOrArray from Utilities */
class SafeParamTest extends \PHPUnit\Framework\TestCase
{
    public function testEmptyKey() : void
    {
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        new SafeParam("", null);
    }
    
    public function testBadKey1() : void
    {
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        new SafeParam("test\0", null);
    }
    
    public function testBadKey2() : void
    {
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        new SafeParam("--test", null);
    }
    
    public function testBadKey3() : void
    {
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        new SafeParam("test 0", null);
    }
    
    public function testBadKey4() : void
    {
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        new SafeParam("%test", null);
    }
    
    /** 
     * @template T
     * @param ScalarOrArray|SafeParams $value
     * @param T $want
     * @param callable(SafeParam):T $func 
     */
    protected function testGood($value, $want, callable $func, bool $willlog = true) : void
    {
        $param = new SafeParam("key",$value);
        
        $logarr = array(); $param->SetLogRef($logarr, 999); // test logging
        
        $this->assertSame($want, $func($param));
        
        if (!$willlog) $this->assertEmpty($logarr); 
        else $this->assertSame(array('key'=>$want),$logarr);
    }
    
    /**
     * @param ScalarOrArray|SafeParams $value 
     * @param callable(SafeParam):mixed $func 
     */
    protected function testBad($value, callable $func) : void
    {            
        $param = new SafeParam("key",$value);
        
        $logarr = array(); $param->SetLogRef($logarr, 999); // test logging
        
        $caught = false; try { $func($param); }
        catch (Exceptions\SafeParamInvalidException $e){ $caught = true; }
        
        $this->assertTrue($caught);
        $this->assertEmpty($logarr); // not logged!
    }
    
    protected function testNulls(callable $get, callable $getn) : void
    {
        $param = new SafeParam('key', null);
        
        $logarr = array(); $param->SetLogRef($logarr, 999); // test logging
        
        $this->assertNull($getn($param));
        $this->assertSame(array('key'=>null),$logarr);
        
        $caught = false; try { $get($param); }
        catch (Exceptions\SafeParamNullValueException $e){ $caught = true; }
        
        $this->assertTrue($caught);
    }
    
    public function testRawValue() : void
    {
        $param = new SafeParam('key',null);
        $this->assertNull($param->GetNullRawValue());
        
        $param = new SafeParam('key',$v='test');
        $this->assertSame($v,$param->GetNullRawValue());

        $param = new SafeParam('key',$v=5);
        $this->assertSame($v,$param->GetNullRawValue());
        
        $param = new SafeParam('key',$v=array(1,2,3,array(4,5,6)));
        $this->assertSame($v,$param->GetNullRawValue());
        
        $param = new SafeParam('key',(new SafeParams())->LoadArray($v=array('test1'=>5)));
        $this->assertSame($v,$param->GetNullRawValue());
    }
    
    public function testRawString() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetRawString(); };
        $getValN = function(SafeParam $p){ return $p->GetNullRawString(); };
        
        $val = Crypto::GenerateSecretKey();
        
        $this->testGood($val, $val, $getVal, false);
        $this->testGood($val, $val, $getValN, false);
        
        $this->testGood(null, null, $getValN, false);
        
        $this->expectException(Exceptions\SafeParamNullValueException::class);
        $this->testGood(null, null, $getVal, false); // not good
    }
    
    public function testTypes() : void
    {
        $this->testBad(array(4,5,6), function(SafeParam $p){ return $p->GetRawString(); });
        $this->testBad(new SafeParams(), function(SafeParam $p){ return $p->GetRawString(); });

        $inner = function(SafeParam $p){ return $p->GetRawString(); };
        $this->testBad("test", function(SafeParam $p)use($inner){ return $p->GetArray($inner); }); // bad json
        $this->testBad(new SafeParams(), function(SafeParam $p)use($inner){ return $p->GetArray($inner); });
        
        $this->testBad("test", function(SafeParam $p){ return $p->GetObject(); }); // bad json
        $this->testBad(array(4,5,6), function(SafeParam $p){ return $p->GetObject(); });
    }

    public function testCheckFunction() : void
    {
        $param = new SafeParam("mykey",$val="testval");
        
        $param->CheckFunction(function(string $p)use($val){
            $this->assertSame($p,$val); return true;
        });
    
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        $param->CheckFunction(function(string $p){ return false; });
    }

    public function testCheckStringLength() : void
    {
        $val = "123456789"; $param = new SafeParam("key", $val);
        $this->assertSame($val, $param->CheckLength(99)->GetNullRawValue());
        
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        $param->CheckLength(5);
    }
    
    public function testCheckArrayLength() : void
    {
        $val = array(1,2,3,4,5,6,7); $param = new SafeParam("key", $val);
        $this->assertSame($val, $param->CheckLength(99)->GetNullRawValue());
        
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        $param->CheckLength(5);
    }

    public function testAutoNull() : void
    {
        $this->assertTrue((new SafeParam("key", null))->isNull());
        $this->assertTrue((new SafeParam("key", ""))->isNull());
        $this->assertTrue((new SafeParam("key", "null"))->isNull());
        $this->assertTrue((new SafeParam("key", "NULL"))->isNull());
        $this->assertFalse((new SafeParam("key", "test"))->isNull());
        
        $getRaw = function(SafeParam $p){ return $p->GetNullRawString(); };
        
        $this->testGood("", null, $getRaw, false);
        $this->testGood("null", null, $getRaw, false);
    }
    
    public function testFromAllowlist() : void
    {
        $getVal = function(SafeParam $p){ return $p->FromAllowlist(array('a')); };
        $getValN = function(SafeParam $p){ return $p->FromAllowlistNull(array('a')); };
        
        $this->testNulls($getVal, $getValN);
        
        $val = "test"; $param = new SafeParam('mykey',$val);
        
        $this->assertSame($val, $param->FromAllowlist(array($val,'a','b')));
        
        $this->expectException(Exceptions\SafeParamInvalidException::class);
        $param->FromAllowlist(array('a','b','c'));
    }
    
    public function testBool() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetBool(); };
        $getValN = function(SafeParam $p){ return $p->GetNullBool(); };
        
        $this->testGood("", true, $getVal);
        $this->testGood("", null, $getValN);
        $this->testGood("null", true, $getVal);
        $this->testGood("null", null, $getValN);
        
        foreach (array("true", "1", "yes ", true, 1) as $val)
        {
            $this->testGood($val, true, $getVal);
            $this->testGood($val, true, $getValN);
        }
        
        foreach (array("false", "0", " no", false, 0) as $val)
        {
            $this->testGood($val, false, $getVal);
            $this->testGood($val, false, $getValN);
        }
        
        $this->testBad("75", $getVal);
        $this->testBad("badvalue", $getVal);
        $this->testBad("anything else?", $getVal);
    }

    public function testBaseInt() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetInt(); };
        $getValN = function(SafeParam $p){ return $p->GetNullInt(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testGood(0, 0, $getVal);
        $this->testGood(123, 123, $getVal);
        
        $this->testGood("0", 0, $getVal);
        $this->testGood(" 1", 1, $getVal);
        $this->testGood("123  ", 123, $getVal);
        $this->testGood(" -123 \n", -123, $getVal);
        
        $this->testBad("text", $getVal);
        $this->testBad("47t", $getVal);
        $this->testBad("t47", $getVal);
    }

    public function testIntRanges() : void
    {
        $getInt = function(SafeParam $p){ return $p->GetInt(); };
        $getInt32 = function(SafeParam $p){ return $p->GetInt32(); };
        $getInt16 = function(SafeParam $p){ return $p->GetInt16(); };
        $getInt8 = function(SafeParam $p){ return $p->GetInt8(); };
        
        $getUint = function(SafeParam $p){ return $p->GetUint(); };
        $getUint32 = function(SafeParam $p){ return $p->GetUint32(); };
        $getUint16 = function(SafeParam $p){ return $p->GetUint16(); };
        $getUint8 = function(SafeParam $p){ return $p->GetUint8(); };

        $this->testBad('-1', $getUint);
        $this->testGood('0', 0, $getUint);
        $this->testGood('1', 1, $getUint);
        
        $this->testBad('-1', $getUint8);
        $this->testGood('0', 0, $getUint8);
        $this->testGood('255', 255, $getUint8);
        $this->testBad('256', $getUint8);
        
        $this->testBad('-1', $getUint16);
        $this->testGood('0', 0, $getUint16);
        $this->testGood('65535', 65535, $getUint16);
        $this->testBad('65536', $getUint16);
        
        $this->testBad('-1', $getUint32);
        $this->testGood('0', 0, $getUint32);
        $this->testGood('4294967295', 4294967295, $getUint32);
        $this->testBad('4294967296', $getUint32);
        
        $this->testGood('-1', -1, $getInt);
        $this->testGood('0', 0, $getInt);
        $this->testGood('1', 1, $getInt);
        
        $this->testBad('-129', $getInt8);
        $this->testGood('-128', -128, $getInt8);
        $this->testGood('127', 127, $getInt8);
        $this->testBad('128', $getInt8);
        
        $this->testBad('-32769', $getInt16);
        $this->testGood('-32768', -32768, $getInt16);
        $this->testGood('32767', 32767, $getInt16);
        $this->testBad('32768', $getInt16);
        
        $this->testBad('-2147483649', $getInt32);
        $this->testGood('-2147483648', -2147483648, $getInt32);
        $this->testGood('2147483647', 2147483647, $getInt32);
        $this->testBad('2147483648', $getInt32);
    }
    
    public function testFloat() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetFloat(); };
        $getValN = function(SafeParam $p){ return $p->GetNullFloat(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testBad("text", $getVal);
        $this->testBad("47t", $getVal);
        $this->testBad("t47", $getVal);
        
        $this->testGood(0, 0.0, $getVal);
        $this->testGood(1.2, 1.2, $getVal);
        
        $this->testGood("0", 0.0, $getVal);
        $this->testGood("123", 123.0, $getVal);
        $this->testGood("-123", -123.0, $getVal);
        
        $this->testGood("123.45", 123.45, $getVal);
        $this->testGood("-123.45", -123.45, $getVal);
    }
    
    public function testRandstr() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetRandstr(); };
        $getValN = function(SafeParam $p){ return $p->GetNullRandstr(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testGood("  test\n", "test", $getVal); // trim
        $this->testGood("9a8s7dn8_s7n", "9a8s7dn8_s7n", $getVal);
        
        $this->testBad("23 45", $getVal);
        $this->testBad("234a<", $getVal);
        $this->testBad("234a'", $getVal);
        $this->testBad("234a\"", $getVal);
    }
    
    public function testAlphanum() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetAlphanum(); };
        $getValN = function(SafeParam $p){ return $p->GetNullAlphanum(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testGood("  test\n", "test", $getVal); // trim
        $this->testGood($val="987n927_83..n4-928", $val, $getVal);
        
        $this->testBad("23 45", $getVal);
        $this->testBad("234a<", $getVal);
        $this->testBad("234a'", $getVal);
        $this->testBad("234a\"", $getVal);
    }
    
    public function testName() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetName(); };
        $getValN = function(SafeParam $p){ return $p->GetNullName(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testGood("  test\n", "test", $getVal); // trim
        $this->testGood($val="(my'te_st..) test", $val, $getVal);
        
        $this->testBad("234a<", $getVal);
        $this->testBad("234a\"", $getVal);
        $this->testBad("234a;", $getVal);
    }
    
    public function testEmail() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetEmail(); };
        $getValN = function(SafeParam $p){ return $p->GetNullEmail(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testGood("  test@mytest.io\n", "test@mytest.io", $getVal); // trim
        $this->testGood($val="mytest123_123@test.mytest.edu", $val, $getVal);
        
        $this->testBad("0", $getVal);
        $this->testBad("yoooo", $getVal);
        $this->testBad("nothing@", $getVal);
        $this->testBad("mytest@serv", $getVal);
        $this->testBad("test<12@test.com", $getVal);
    }
    
    public function testFSName() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetFSName(); };
        $getValN = function(SafeParam $p){ return $p->GetNullFSName(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testGood("  test\n", "test", $getVal); // trim
        $this->testGood($val="my %file.txt", $val, $getVal);
        
        $this->testBad("./test", $getVal);
        $this->testBad("../test", $getVal);
        $this->testBad("test/test", $getVal);
        $this->testBad("test/../test", $getVal);
        $this->testBad("test\\test", $getVal);
        
        $this->testBad("test:", $getVal);
        $this->testBad("test?", $getVal);

        $this->testBad(".", $getVal);
        $this->testBad("..", $getVal);
        $this->testBad("/..", $getVal);
        $this->testBad("/../", $getVal);
        $this->testBad("../", $getVal);
    }
    
    public function testFSPath() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetFSPath(); };
        $getValN = function(SafeParam $p){ return $p->GetNullFSPath(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testGood("  test\n", "test", $getVal); // trim
        $this->testGood($val="my \$file.txt", $val, $getVal);
        
        $this->testGood($val="./test", $val, $getVal);
        $this->testGood($val="../test", $val, $getVal);
        $this->testGood($val="test/test", $val, $getVal);
        $this->testGood($val="test/../test", $val, $getVal);
        $this->testGood($val="test\\test", $val, $getVal);
        $this->testGood($val="C:\\test", $val, $getVal);
        
        $this->testBad("test?", $getVal); 
        $this->testBad("smb://fileserver", $getVal);
        $this->testBad("http://test.com/test.txt", $getVal);
    }
    
    public function testHostname() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetHostname(); };
        $getValN = function(SafeParam $p){ return $p->GetNullHostname(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testGood("  test\n", "test", $getVal); // trim
        $this->testGood($val='simple', $val, $getVal);
        $this->testGood($val='test.com', $val, $getVal);
        $this->testGood($val='sub.test1.my-test.tk', $val, $getVal);
        
        $this->testBad('ftp://test.com', $getVal);
        $this->testBad('no_underscores', $getVal);
    }

    public function testHTMLText() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetHTMLText(); };
        $getValN = function(SafeParam $p){ return $p->GetNullHTMLText(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testGood($val="hello! this is some text. ", $val, $getVal); // NO trimming
        
        $this->testGood("stripping <tags>? &\n", "stripping &#60;tags&#62;? &#38;&#10;", $getVal);
    }

    public function testUTF8String() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetUTF8String(); };
        $getValN = function(SafeParam $p){ return $p->GetNullUTF8String(); };
        
        $this->testNulls($getVal, $getValN);
        
        $this->testGood($val="0", $val, $getVal);
        $this->testGood($val="hello! special chars !@#$%^&*()\u{9999}", $val, $getVal);
        
        $this->testBad("\0", $getVal);
        $this->testBad((string)hex2bin("deadbeef"), $getVal);
    }
    
    public function testObject() : void
    {
        $getVal = function(SafeParam $p){ return $p->GetObject(); };
        $getValN = function(SafeParam $p){ return $p->GetNullObject(); };
        
        $this->testNulls($getVal, $getValN);
        
        $testobj = (new SafeParams())->LoadArray(['key1'=>75,'key2'=>'val1']);
        $json = '{"key1":75,"key2":"val1"}'; // can take json or SafeParams
        
        foreach (array($json, $testobj) as $val)
        {
            $param = new SafeParam('key',$val);
            $obj = $param->GetObject();
            
            $this->assertSame($obj->GetParam('key1')->GetInt(), 75);
            $this->assertSame($obj->GetParam('key2')->GetAlphanum(), 'val1');
            
            $this->assertSame(array('key1'=>75,'key2'=>'val1'), $obj->GetAllRawValues());
            
            $this->testBad("string", $getVal);
            $this->testBad("{test:10}", $getVal);
            $this->testBad("{'single':10}", $getVal);
        }
    }

    public function testArray() : void
    {
        $getInt = function(SafeParam $p){ return $p->GetInt(); };
        
        $getIntArr = function(SafeParam $p)use($getInt){ return $p->GetArray($getInt); };
        $getIntArrN = function(SafeParam $p)use($getInt){ return $p->GetNullArray($getInt); };
        
        $this->testNulls($getIntArr, $getIntArrN);
        
        // can take JSON or a real array
        foreach (["[5,7,9,27]",array(5,7,9,27),
            array('0'=>'5','1'=>'7','2'=>'9','3'=>'27')] as $val)
        {
            $param = new SafeParam('key',$val);
            
            $logarr = array(); $param->SetLogRef($logarr, 999);
            
            $arr = $param->GetArray($getInt);
            $this->assertSame($arr, array(5,7,9,27));
            
            $this->assertSame(array('key'=>[5,7,9,27]), $logarr);
    
            $this->testBad('a string', $getIntArr);
            $this->testBad('[57,"mixed"]', $getIntArr);
        }
    }
    
    public function testObjectLogging() : void
    {
        $json = '{"key1":75,"key2":"val1"}';
        $param = new SafeParam('key',$json);
        
        $obj = $param->GetObject(); // no logging
        $obj->GetParam('key1')->GetInt();
        $obj->GetParam('key2')->GetAlphanum();
        
        $logarr = array(); $param->SetLogRef($logarr, 999); // full logging
        
        $obj = $param->GetObject();
        $this->assertSame(array('key'=>array()), $logarr);
        $obj->GetParam('key1')->GetInt();
        $obj->GetParam('key2')->GetAlphanum();
        $this->assertSame(array('key'=>array('key1'=>75,'key2'=>'val1')), $logarr);
        
        // test inherit level - SafeParam will log, sub-SafeParams will not
        $logarr = array(); $param->SetLogRef($logarr, 0);
        
        $obj = $param->GetObject();
        $this->assertSame(array('key'=>array()), $logarr);
        $obj->GetParam('key1')->GetInt();
        $obj->GetParam('key2')->GetAlphanum();
        $this->assertSame(array('key'=>array()), $logarr);
    }

    public function testNestedObjectsAndArrays() : void
    {
        $getInt = function(SafeParam $p){ return $p->GetInt(); };
        
        $json = '[{"key1":[5,6,7,8],"key2":{"inner1":"val1","inner2":[1,2,3]}},{"key1":"test"},{"key2":75}]';
        $param = new SafeParam('mykey',$json);
        
        $logarr = array(); $param->SetLogRef($logarr, 999); // full logging
        
        $arr = $param->GetObjectArray();
        $this->assertCount(3, $arr);
        
        $obj1 = $arr[0];
        
        $key1 = $obj1->GetParam('key1')->GetArray($getInt);
        $this->assertSame($key1, array(5,6,7,8));

        $key2 = $obj1->GetParam('key2')->GetObject();
        $inner1 = $key2->GetParam('inner1')->GetAlphanum();
        $this->assertSame($inner1, "val1");
        
        $inner2 = $key2->GetParam('inner2')->GetArray($getInt);
        $this->assertSame($inner2, array(1,2,3));
        
        $obj2 = $arr[1]; $obj3 = $arr[2];
        
        $this->assertSame("test", $obj2->GetParam("key1")->GetAlphanum());
        $this->assertSame(75, $obj3->GetParam("key2")->GetInt());
        
        $this->assertSame(array('mykey'=>Utilities::JSONDecode($json)), $logarr);
    }
}
