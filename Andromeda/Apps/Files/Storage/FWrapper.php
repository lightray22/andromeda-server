<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Apps\Files\FileUtils;

/** An open file handle */
class FileContext
{
    /** @param resource $handle */
    public function __construct(
        public $handle, 
        public int $offset,
        public bool $isWrite){}
}

/**
 * A storage that uses PHP's fwrapper functions
 * @see https://www.php.net/manual/en/wrappers.php
 */
abstract class FWrapper extends Storage
{
    /** Returns the full fwrapper URL for the given path */
    protected abstract function GetFullURL(string $path = "") : string;
    
    public function ItemStat(string $path) : ItemStat
    {
        $data = @stat($this->GetFullURL($path));
        if ($data === false) throw new Exceptions\ItemStatFailedException($path);
        return new ItemStat($data['atime'], $data['ctime'], $data['mtime'], $data['size']);
    }
    
    public function isFolder(string $path) : bool
    {
        return is_dir($this->GetFullURL($path));
    }
    
    public function isFile(string $path) : bool
    {
        return is_file($this->GetFullURL($path));
    }
    
    protected function assertReadable() : void
    {
        if (!is_readable($this->GetFullURL()))
            throw new Exceptions\TestReadFailedException();
    }
    
    protected function assertWriteable() : void
    {
        if (!is_writeable($this->GetFullURL()))
            throw new Exceptions\TestWriteFailedException();
    }

    protected function SubReadFolder(string $path) : array
    {
        if (($list = @scandir($this->GetFullURL($path), SCANDIR_SORT_NONE)) === false)
            throw new Exceptions\FolderReadFailedException($path);
        return array_filter($list, function($item){ return $item !== "." && $item !== ".."; });
    }
    
    protected function SubCreateFolder(string $path) : parent
    {
        if (!@mkdir($this->GetFullURL($path))) 
            throw new Exceptions\FolderCreateFailedException($path);
        else return $this;
    }
    
    protected function SubCreateFile(string $path) : parent
    {
        if (file_put_contents($this->GetFullURL($path),'') === false)
            throw new Exceptions\FileCreateFailedException($path);
        return $this;
    }

    protected function SubImportFile(string $src, string $dest, bool $istemp) : parent
    {
        $this->ClosePath($dest);
        
        if (!copy($src, $this->GetFullURL($dest)))
            throw new Exceptions\FileCopyFailedException($src);
        return $this;
    }
    
    protected function SubTruncate(string $path, int $length) : parent
    {
        $this->ClosePath($path);
        
        if (($handle = static::OpenWriteHandle($path)) === false)
            throw new Exceptions\FileWriteFailedException($path);

        if (!ftruncate($handle, $length))
            throw new Exceptions\FileWriteFailedException($path);
        
        if (!fclose($handle))
            throw new Exceptions\FileWriteFailedException($path);
        
        return $this;
    }    
    
    protected function SubDeleteFile(string $path) : parent
    {
        $this->ClosePath($path);
        
        if (!unlink($this->GetFullURL($path))) 
            throw new Exceptions\FileDeleteFailedException($path);
        else return $this;
    }    
    
    protected function SubDeleteFolder(string $path) : parent
    {
        if (!@rmdir($this->GetFullURL($path))) 
            throw new Exceptions\FolderDeleteFailedException($path);
        else return $this;
    }

    protected function SubRenameFile(string $old, string $new) : parent
    {
        $this->ClosePath($old);
        $this->ClosePath($new);
        
        if (!rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new Exceptions\FileRenameFailedException($old);
        return $this;
    }
    
    protected function SubRenameFolder(string $old, string $new) : parent
    {
        if (!@rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new Exceptions\FolderRenameFailedException($old);
        return $this;
    }
    
    protected function SubMoveFile(string $old, string $new) : parent
    {
        $this->ClosePath($old);
        $this->ClosePath($new);
        
        if (!rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new Exceptions\FileMoveFailedException($old);
        return $this;
    }
    
    protected function SubMoveFolder(string $old, string $new) : parent
    {
        if (!@rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new Exceptions\FolderMoveFailedException($old);
        return $this;
    }
    
    protected function SubCopyFile(string $old, string $new) : parent
    {
        $this->ClosePath($new);
        
        if (!copy($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new Exceptions\FileCopyFailedException($old);
        return $this;
    }
    
    protected function SubReadBytes(string $path, int $start, int $length) : string
    {
        $context = $this->GetContext($path, $start, false);
        $data = FileUtils::ReadStream($context->handle, $length);
        $context->offset += $length;
        return $data;
    }
    
    protected function SubWriteBytes(string $path, int $start, string $data) : self
    {        
        $context = $this->GetContext($path, $start, true);
        FileUtils::WriteStream($context->handle, $data);
        $context->offset += strlen($data);
        return $this;
    }
    
    /** @var array<string, FileContext> map for all file handles */
    private $contexts = array();
    
    /** Returns true if we can read from a stream opened as write */
    protected static function supportsReadWrite() : bool { return true; }
    
    /** Returns true if an already-open stream can be seeked randomly */
    protected static function supportsSeekReuse() : bool { return true; }
    
    /** 
     * Returns a read handle for the given path
     * @return resource|false
     */
    protected function OpenReadHandle(string $path) { 
        return fopen($this->GetFullURL($path),'rb'); }
    
    /** 
     * Returns a write handle for the given path
     * @return resource|false
     */
    protected function OpenWriteHandle(string $path) { 
        return fopen($this->GetFullURL($path),'rb+'); }
    
    /**
     * Returns a new handle for the given path
     * @param string $path path of file
     * @param non-negative-int $offset offset to initialize to
     * @param bool $isWrite true if this is a write
     * @throws Exceptions\FileOpenFailedException if opening fails
     * @throws Exceptions\FileSeekFailedException if seeking fails
     * @return FileContext new file context
     */
    protected function OpenContext(string $path, int $offset, bool $isWrite) : FileContext
    {
        $handle = $isWrite ? $this->OpenWriteHandle($path) : $this->OpenReadHandle($path);
        
        if ($handle === false) 
            throw new Exceptions\FileOpenFailedException($path);
        
        if (fseek($handle, $offset) !== 0)
            throw new Exceptions\FileSeekFailedException($path);
        
        return new FileContext($handle, $offset, false);
    }

    /**
     * Returns a context for the given file
     * @param string $path path to file
     * @param non-negative-int $offset desired byte offset
     * @param bool $isWrite true if write access is needed
     * @return FileContext file stream context
     */
    protected function GetContext(string $path, int $offset, bool $isWrite) : FileContext
    {
        $context = $this->contexts[$path] ?? null;

        if ($context !== null)
        {
            // close the stream if we want to write to a read stream
            $close = ($isWrite && !$context->isWrite);
            
            // close the stream if we want to read from a write stream and that's unsupported
            $close = $close || (!$isWrite && $context->isWrite && !static::supportsReadWrite());
            
            // close the stream if its offset is wrong and we can't seek it
            $close = $close || ($context->offset !== $offset && !static::supportsSeekReuse());
            
            if ($close) { $this->ClosePath($path); $context = null; }
        }

        $context ??= $this->OpenContext($path, $offset, $isWrite);
        
        if ($context->offset !== $offset)
        {
            if (fseek($context->handle, $offset) !== 0)
                throw new Exceptions\FileSeekFailedException($path);
                
            $context->offset = $offset;
        }
        
        $this->contexts[$path] = $context; return $context;
    }
        
    /** Closes any open handles for the given file path */
    protected function ClosePath(string $path) : void
    {
        if (array_key_exists($path, $this->contexts))
        {
            if (!fclose($this->contexts[$path]->handle))
                throw new Exceptions\FileCloseFailedException($path);
                
            unset($this->contexts[$path]);
        }
    }
}
