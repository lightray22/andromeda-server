<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; require_once("init.php");

use Andromeda\Core\Utilities;

class DummyInterface extends IOInterface
{
    private Input $myinput;
    private int $numcalls = 0;
    public function __construct(Input $input) { $this->myinput = $input; parent::__construct(); }
    public function getInputCalls() : int { return $this->numcalls; }
    
    public static function isPrivileged(): bool     { return true; }
    public static function isInteractive(): bool    { return true; }
    protected function LoadInput(): Input       { $this->numcalls++; return $this->myinput; }
    public static function GetDefaultOutmode(): int { return 75; }
    public static function isApplicable(): bool     { return true; }
    public function getUserAgent(): string          { return ""; }
    public function getAddress(): string            { return ""; }
    public function FinalOutput(Output $output): void { }
}

class IOInterfaceTest extends \PHPUnit\Framework\TestCase
{
    public function testGetInput() : void
    {
        $input = $this->createMock(Input::class);
        $iface = new DummyInterface($input);
        
        $this->assertSame($input, $iface->GetInput());
        $this->assertSame($input, $iface->GetInput());
        $this->assertSame(1, $iface->getInputCalls());
        $this->assertSame(75, $iface->GetOutputMode());
    }
    
    public function testOutputMode() : void
    {
        $input = $this->createMock(Input::class);
        $iface = new DummyInterface($input);
        
        foreach (IOInterface::OUTPUT_TYPES as $mode)
            $this->assertSame($mode,$iface->SetOutputMode($mode)->GetOutputMode());

        $iface->SetOutputMode(IOInterface::OUTPUT_PLAIN);
        $iface->SetOutputHandler(new OutputHandler(function(){ return null; },function(Output $output){ }));
        $this->assertSame(IOInterface::OUTPUT_PLAIN, $iface->GetOutputMode()); // null bytes does not affect outmode
        
        $iface->SetOutputHandler(null); // reset
        $iface->SetOutputHandler(new OutputHandler(function(){ return 0; },function(Output $output){ }));
        $this->assertSame(0, $iface->GetOutputMode()); // 1st user func sets null
        
        $this->expectException(Exceptions\MultiOutputException::class);
        $iface->SetOutputMode(IOInterface::OUTPUT_PLAIN);
    }
    
    public function testUserOutputs() : void
    {
        $iface = new DummyInterface($this->createMock(Input::class));
        $output = $this->createStub(Output::class);

        $this->assertSame("", Utilities::CaptureOutput(function()use($iface,$output){
            $this->assertSame(false, $iface->UserOutput($output));
        }));

        $outfunc = $this->createMock(OutputHandler::class);
        $outfunc->method('GetBytes')->willReturn(null);
        $called = false;
        $outfunc->method('DoOutput')->willReturnCallback(
            function()use(&$called){ $called = true; });
        $iface->SetOutputHandler(null); // reset
        $iface->SetOutputHandler($outfunc);
        $this->assertSame("", Utilities::CaptureOutput(function()use($iface,$output){
            $this->assertSame(true, $iface->UserOutput($output));
        }));
        $this->assertTrue($called);

        $outfunc = $this->createMock(OutputHandler::class);
        $outfunc->method('GetBytes')->willReturn($len=55);
        $outdata = Utilities::Random($len);
        $outfunc->method('DoOutput')->willReturnCallback(
            function()use($outdata){ echo $outdata; });
        $iface->SetOutputHandler(null); // reset
        $iface->SetOutputHandler($outfunc);
        $this->assertSame($outdata, Utilities::CaptureOutput(function()use($iface,$output){
            $this->assertSame(true, $iface->UserOutput($output));
        }));

        $this->expectException(Exceptions\MultiOutputException::class);
        $iface->SetOutputHandler($outfunc); // set twice
    }
}
