<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Filesystem; require_once("init.php");

use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/Apps/Files/Storage/Storage.php"); use Andromeda\Apps\Files\Storage\{Storage, ItemStat};

require_once(ROOT."/Apps/Files/Filesystem/FSManager.php");

require_once(ROOT."/Apps/Files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/Apps/Files/Folder.php"); use Andromeda\Apps\Files\Folder;
require_once(ROOT."/Apps/Files/SubFolder.php"); use Andromeda\Apps\Files\SubFolder;

abstract class MockFile extends File
{
    private ExternalTest $test; private array $dbfiles; 
    
    public function __construct(ExternalTest $test, array& $dbfiles){ $this->test = $test; $this->dbfiles = &$dbfiles; }
    
    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : File
    {
        return $this->test->NotifyCreateFile($this->dbfiles, $database, $parent, $account, $name);
    }
}

abstract class MockFolder extends Folder
{
    private ExternalTest $test; private array $dbfolders;
    
    public function __construct(ExternalTest $test, array& $dbfolders){ $this->test = $test; $this->dbfolders = &$dbfolders; }
    
    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : SubFolder
    {
        return $this->test->NotifyCreateFolder($this->dbfolders, $database, $parent, $account, $name);
    }
}

class ExternalTest extends \PHPUnit\Framework\TestCase
{
    protected function getMockItem(string $class, string $name, ?Folder $parent)
    {
        $item = $this->createMock($class);
        
        $item->method('GetName')->willReturn($name);
        $item->method('GetParent')->willReturn($parent);
        
        return $item;
    }
    
    protected function getMockRoot(string $rpath)
    {        
        $path = explode('/',$rpath); $name = array_pop($path); $path = implode('/',$path);
        
        $parent = $name ? $this->getMockRoot($path) : null;
        
        return $this->getMockItem(Folder::class, $name, $parent);
    }
    
    public function NotifyCreateFile(array& $dbfiles, ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : File
    {
        $this->assertFalse(in_array($name, array_map(function(File $file){ return $file->GetName(); }, $dbfiles),true));
        
        return $dbfiles[] = $this->getMockItem(File::class, $name, $parent);
    }
    
    public function NotifyCreateFolder(array& $dbfolders, ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : SubFolder
    {
        $this->assertFalse(in_array($name, array_map(function(SubFolder $folder){ return $folder->GetName(); }, $dbfolders),true));
        
        return $dbfolders[] = $this->getMockItem(SubFolder::class, $name, $parent);
    }
    
    // thoroughly tests both GetItemPath() and RefreshFolder()
    protected function testFolderSync(string $rpath, array $fsfiles, array $fsfolders, array $dbfiles, array $dbfolders)
    {        
        $folder = $this->getMockRoot($rpath);
        
        $dbfiles = array_map(function($name)use($folder){ 
            return $this->getMockItem(File::class, $name, $folder); }, $dbfiles);
        $dbfolders = array_map(function($name)use($folder){ 
            return $this->getMockItem(SubFolder::class, $name, $folder); }, $dbfolders);
        
        foreach ($dbfiles as $fname=>$dbfile)
            $dbfile->method('NotifyFSDeleted')->will($this->returnCallback(
                function()use(&$dbfiles,$fname){ unset($dbfiles[$fname]); }));
        
        foreach ($dbfolders as $fname=>$dbfolder)
            $dbfolder->method('NotifyFSDeleted')->will($this->returnCallback(
                function()use(&$dbfolders,$fname){ unset($dbfolders[$fname]); }));
            
        $fsfiles = array_map(function($name)use($rpath){ return "$rpath/$name"; }, $fsfiles);
        $fsfolders = array_map(function($name)use($rpath){ return "$rpath/$name"; }, $fsfolders);
        
        $storage = $this->createMock(Storage::class);
        $storage->method('ItemStat')->willReturn(new ItemStat());
        
        $storage->method('isFile')->will($this->returnCallback(
            function(string $path)use($fsfiles) : bool
        {
            return in_array($path, $fsfiles, true);
        }));
        
        $storage->method('isFolder')->will($this->returnCallback(
            function(string $path)use($rpath,$fsfolders) : bool
        {
            return $path === $rpath || in_array($path, $fsfolders, true);
        }));
        
        $storage->method('readFolder')->will($this->returnCallback(
            function(string $path)use($rpath,$fsfiles,$fsfolders) : array
        {            
            if ($path !== $rpath) return array();            
            $contents = array_merge($fsfiles, $fsfolders);
            
            return array_map(function($path){ return basename($path); }, $contents);
        }));
        
        $filesystem = $this->createMock(FSManager::class);
        $filesystem->method('GetStorage')->willReturn($storage);
        
        $fsimpl = new External($filesystem);
        
        $folder->method('GetFiles')->will($this->returnCallback(function()use($dbfiles){ return $dbfiles; }));
        $folder->method('GetFolders')->will($this->returnCallback(function()use($dbfolders){ return $dbfolders; }));

        $fsimpl->RefreshFolder($folder, true, MockFile::class, MockFolder::class);
        
        $fsfiles = array_map(function($path){ return basename($path); }, $fsfiles);
        $fsfolders = array_map(function($path){ return basename($path); }, $fsfolders);
        
        $dbfiles = array_map(function(File $file){ return $file->GetName(); }, $dbfiles);
        $dbfolders = array_map(function(SubFolder $folder){ return $folder->GetName(); }, $dbfolders);
        
        sort($dbfiles); sort($dbfolders); 
        sort($fsfiles); sort($fsfolders);
        
        $this->assertEquals($fsfiles, $dbfiles);
        $this->assertEquals($fsfolders, $dbfolders);
    }

    protected function testFolderSyncs(string $root)
    {
        // basic adding items
        $this->testFolderSync($root, array(), array(), array(), array());
        $this->testFolderSync($root, array('myfile'), array(), array(), array());
        $this->testFolderSync($root, array(), array('myfolder'), array(), array());        
        $this->testFolderSync($root, array('myfile'), array('myfolder'), array(), array());
        
        // items already exist
        $this->testFolderSync($root, array(), array(), array('myfile'), array('myfolder'));
        $this->testFolderSync($root, array('myfile'), array(), array('myfile'), array('myfolder'));
        $this->testFolderSync($root, array(), array('myfolder'), array('myfile'), array('myfolder'));
        $this->testFolderSync($root, array('myfile'), array('myfolder'), array('myfile'), array('myfolder'));
        
        // extraneous dbitems
        $this->testFolderSync($root, array(), array(), array('myfile1','myfile2'), array('myfolder1','myfolder2'));
        $this->testFolderSync($root, array('myfile'), array(), array('myfile1','myfile2'), array('myfolder1','myfolder2'));
        $this->testFolderSync($root, array(), array('myfolder'), array('myfile1','myfile2'), array('myfolder1','myfolder2'));
        $this->testFolderSync($root, array('myfile'), array('myfolder'), array('myfile1','myfile2'), array('myfolder1','myfolder2'));
        
        $this->testFolderSync($root, array('1','2','3b','4','5','6','7'), array(), array('2','3b','5'), array());
        $this->testFolderSync($root, array(), array('2','3','5'), array(), array('1','2','3','4','5','6','7'));
        
        $this->testFolderSync($root, array('3','4','29','8','1','5'), array('2','7','6'), array('22','27','26'), array('23','24','9','0','00','25'));
        $this->testFolderSync($root, array('22','27','26'), array('23','24','9','0','00','25'), array('3','4','29','8','1','5'), array('2','7','6'));
    }
    
    public function testFolderSyncs1(){ $this->TestFolderSyncs(""); }
    public function testFolderSyncs2(){ $this->TestFolderSyncs("/test"); }
    public function testFolderSyncs3(){ $this->TestFolderSyncs("/test1/test2"); }
    public function testFolderSyncs4(){ $this->TestFolderSyncs("/test1/test2/test3"); }
}
