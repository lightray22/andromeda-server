<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; require_once("init.php");

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Apps\Files\Filesystem;

/** basic tests for Storage, FWrapper, and Local */
class LocalTest extends \PHPUnit\Framework\TestCase
{
    /** @var array<string> $tmpfolders */
    private array $tmpfolders = array();

    public static function rrmdir(string $path) : void
    {
        $dir = opendir($path);
        assert(is_resource($dir));
        while(($file = readdir($dir)) !== false)
        {
            if (( $file !== '.' ) && ( $file !== '..' ))
            {
                $full = "$path/$file";
                if (is_dir($full)) self::rrmdir($full);
                else unlink($full);
            }
        }
        closedir($dir);
        rmdir($path);
    }

    public function tearDown() : void
    {
        foreach ($this->tmpfolders as $tmpfolder)
            self::rrmdir($tmpfolder); // recursive
    }

    /** @param array<string,?scalar> $data */
    protected function getStorage(array $data = []) : Local
    {
        $data['id'] = Utilities::Random(12);
        
        $path = sys_get_temp_dir()."/a2unit_".Utilities::Random(8);
        mkdir($this->tmpfolders[] = $data['path'] = $path);

        $database = $this->createMock(ObjectDatabase::class);
        return new Local($database, $data, false);
    }

    public function testBasicStorage() : void
    {
        $storage = $this->getStorage();
        $this->assertFalse($storage->isUserOwned());
        $this->assertNull($storage->TryGetOwner());
        $this->assertNull($storage->TryGetOwnerID());
        $this->assertSame(Storage::DEFAULT_NAME, $storage->GetName());
        $this->assertFalse($storage->isReadOnly());

        $this->assertTrue($storage->canGetFreeSpace());
        $this->assertTrue($storage->supportsFolders());
        $this->assertFalse($storage->usesBandwidth());

        $storage->SetName($name='mytest');
        $this->assertSame($name, $storage->GetName());
        $storage->SetName(null);
        $this->assertSame(Storage::DEFAULT_NAME, $storage->GetName());

        $storage->Test(false); // tmp is writeable
        $this->assertGreaterThan(0, $storage->GetFreeSpace());
    }

    public function testGetFilesystem() : void
    {
        $storage = $this->getStorage(['fstype'=>Storage::FSTYPE_NATIVE]);
        $this->assertFalse($storage->isEncrypted());
        $this->assertFalse($storage->isExternal());
        $this->assertInstanceOf(Filesystem\Native::class, $storage->GetFilesystem());

        $storage = $this->getStorage(['fstype'=>Storage::FSTYPE_NATIVE_CRYPT,'crypto_masterkey'=>'aaa','crypto_chunksize'=>50]);
        $this->assertTrue($storage->isEncrypted());
        $this->assertFalse($storage->isExternal());
        $fs = $storage->GetFilesystem();
        assert($fs instanceof Filesystem\NativeCrypt);
        $this->assertSame(50, $fs->GetChunkSize());

        $storage = $this->getStorage(['fstype'=>Storage::FSTYPE_EXTERNAL]);
        $this->assertFalse($storage->isEncrypted());
        $this->assertTrue($storage->isExternal());
        $this->assertInstanceOf(Filesystem\External::class, $storage->GetFilesystem());

        $storage = $this->getStorage(['fstype'=>999]);
        $this->expectException(Exceptions\InvalidFSTypeException::class);
        $storage->GetFilesystem();
    }

    public function testBasicOps() : void
    {
        $storage = $this->getStorage();
        $storage->ItemStat(""); // root
        $this->assertFalse($storage->isFile(""));
        $this->assertTrue($storage->isFolder(""));

        $storage->CreateFile($p="testfile");
        $s = $storage->ItemStat($p);
        $this->assertGreaterThan(0, $s->atime);
        $this->assertGreaterThan(0, $s->ctime);
        $this->assertGreaterThan(0, $s->mtime);
        $this->assertTrue($storage->isFile($p));
        $this->assertFalse($storage->isFolder($p));

        $storage->CreateFolder($p="testfolder");
        $s = $storage->ItemStat($p);
        $this->assertGreaterThan(0, $s->atime);
        $this->assertGreaterThan(0, $s->ctime);
        $this->assertGreaterThan(0, $s->mtime);
        $this->assertFalse($storage->isFile($p));
        $this->assertTrue($storage->isFolder($p));
        
        $storage->CreateFile($p="testfolder/test2");
        $storage->ItemStat($p);
        $this->assertTrue($storage->isFile($p));
        $this->assertFalse($storage->isFolder($p));

        $this->expectException(Exceptions\ItemStatFailedException::class);
        $storage->ItemStat('testfolder/nonexistent');
    }

    public function testReadFolder() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p="mytest");
        $storage->CreateFolder("$p/2");
        $storage->CreateFile("$p/3.txt");
        $this->assertSame(['2','3.txt'], array_values($storage->ReadFolder($p)));

        $this->expectException(Exceptions\FolderReadFailedException::class);
        $storage->ReadFolder("$p/3.txt");
    }

    public function testCreateFolder() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder("mytest");
        $storage->CreateFolder("mytest/2");
        $storage->CreateFolder("mytest/2/3");

        $this->expectException(Exceptions\FolderCreateFailedException::class);
        $storage->CreateFolder("myfolder1/myfolder2");
    }

    public function testDeleteFolder() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p="mytest12345");
        $storage->DeleteFolder($p);

        $storage->CreateFolder($p);
        $storage->CreateFile("$p/test.txt");

        $this->expectException(Exceptions\FolderDeleteFailedException::class);
        $storage->DeleteFolder($p);
    }

    public function testRenameFolder() : void
    {
        $storage = $this->getStorage();

        $storage->CreateFolder($p="mytest1");
        $storage->CreateFile("$p/test.txt");

        $storage->RenameFolder($p, $p="mytest2");
        $this->assertSame(['test.txt'], array_values($storage->ReadFolder($p)));

        $this->expectException(Exceptions\FolderRenameFailedException::class);
        $storage->RenameFolder("222$p", "none");
    }

    public function testMoveFolder() : void
    {
        $storage = $this->getStorage();

        $storage->CreateFolder($p1="mytest1");
        $storage->CreateFolder($p2="mytest2");
        $storage->CreateFile("$p2/test.txt");

        $storage->MoveFolder($p2, $p2="$p1/$p2");
        $this->assertSame(['test.txt'], array_values($storage->ReadFolder($p2)));

        $this->expectException(Exceptions\FolderMoveFailedException::class);
        $storage->MoveFolder($p2, "$p2/$p1");
    }
    
    // TODO RAY !! file tests
    /*CreateFile
    ImportFile
    ReadBytes
    WriteBytes
    Truncate
    DeleteFile
    RenameFile
    MoveFile
    MoveFolder
    CopyFile*/

    // TODO TEST test the ClosePath - copy something over a file with an open handle
    // TODO TEST rollback detection too commit/rollback/commitAll/rollbackAll
}
