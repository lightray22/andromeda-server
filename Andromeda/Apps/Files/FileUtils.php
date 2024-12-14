<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\ErrorManager;
use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Apps/Files/Storage/Exceptions.php"); use Andromeda\Apps\Files\Storage\{FileReadFailedException, FileWriteFailedException};

/** Helper class for reading/writing streams and files via chunks */
class FileUtils
{    
    /**
     * Reads from the given stream (since fread may only return 8K)
     * @param resource $stream stream to read from
     * @param int $bytes number of bytes to read
     * @param bool $strict if true, want exactly $bytes, else up to
     * @throws FileReadFailedException if reading the stream fails
     * @return string read data
     */
    public static function ReadStream($stream, int $bytes, bool $strict = true) : string
    {
        $byte = 0; $data = array();
        
        while (!feof($stream) && $byte < $bytes)
        {
            $read = fread($stream, $bytes-$byte);
            
            if (!is_string($read))
                throw new FileReadFailedException();
                
            $data[] = $read; $byte += strlen($read);
        }
        
        $data = implode($data);
        
        if ($strict && strlen($data) !== $bytes)
        {
            ErrorManager::GetInstance()->LogDebugInfo(array(
                'read'=>strlen($data), 'wanted'=>$bytes));
            
            throw new FileReadFailedException();
        }
        
        return $data;
    }
    
    /**
     * Writes data to the given stream
     * @param resource $stream stream to write
     * @param string $data data to write
     * @throws FileWriteFailedException if fails
     */
    public static function WriteStream($stream, string $data) : void
    {
        $written = 0; while ($written < strlen($data))
        {
            $piece = $written ? substr($data, $written) : $data;
            
            $bytes = fwrite($stream, $piece);
            
            if ($bytes === false) 
                throw new FileWriteFailedException();
            
            $written += $bytes;
        }
        
        if ($written !== strlen($data))
        {
            ErrorManager::GetInstance()->LogDebugInfo(array(
                'wrote'=>$written, 'wanted'=>strlen($data)));
            
            throw new FileWriteFailedException();
        }
    }

    /**
     * Performs a chunked File read, echoing output and counting bandwidth
     * @param File $file file to read from
     * @param int $fstart first byte to read
     * @param int $flast last byte to read (inclusive!)
     * @param int $chunksize read chunk size
     * @param bool $align if true, align reads to chunk size multiples
     * @param bool $debugdl if true, don't actually echo anything
     * @throws FileReadFailedException if reading the file fails
     */
    public static function DoChunkedRead(File $file, int $fstart, int $flast, int $chunksize, bool $align, bool $debugdl = false) : void
    {
        for ($byte = $fstart; $byte <= $flast; )
        {
            if (connection_aborted()) break;
            
            // the next read should begin on a chunk boundary if aligned
            if (!$align) $nbyte = $byte + $chunksize;
            else $nbyte = (intdiv($byte, $chunksize) + 1) * $chunksize;
            
            $rlen = min($nbyte - $byte, $flast - $byte + 1);
            
            $data = $file->ReadBytes($byte, $rlen);
            
            if (strlen($data) != $rlen)
                throw new FileReadFailedException();
                
            $file->CountBandwidth($rlen); $byte += $rlen;
            
            if (!$debugdl) { echo $data; flush(); }
        }
    }

    /**
     * Perform a chunked write to a file
     * @param resource $handle input data handle
     * @param File $file write destination
     * @param int $wstart write offset
     * @param int $chunksize write chunksize
     * @param bool $align if true, align to chunksize multiples
     * @throws FileReadFailedException if reading input fails
     * @return int number of bytes written
     */
    public static function DoChunkedWrite($handle, File $file, int $wstart, int $chunksize, bool $align) : int
    {
        $wbyte = $wstart; while (!feof($handle))
        {
            // the next write should begin on a chunk boundary if aligned
            if (!$align) $nbyte = $wbyte + $chunksize;
            else $nbyte = (intdiv($wbyte, $chunksize) + 1) * $chunksize;
            
            $data = self::ReadStream($handle, $nbyte-$wbyte, false);
            
            if (!strlen($data)) continue; // stream could be 0 bytes
            
            $file->WriteBytes($wbyte, $data); $wbyte += strlen($data);
        }
        
        return $wbyte-$wstart;
    }    
    
    /**
     * Returns the chunk size to use for reading/writing a file
     *
     * Based on the configured RW chunk size and the file's FS chunk size
     * We want to use the RW size but MUST use a multiple of the FS size
     * @param int $chunksize the configured RW chunk size
     * @param ?int $fschunksize filesystem chunksize (or null)
     * @return int chunk size in bytes
     */
    public static function GetChunkSize(int $chunksize, ?int $fschunksize = null) : int
    {
        $align = ($fschunksize !== null);
        // transfer chunk size must be an integer multiple of the FS chunk size
        if ($align) $chunksize = ceil($chunksize/$fschunksize)*$fschunksize;
        
        return $chunksize;
    }
    
    /**
     * Peform a chunked file read to stdout
     * @param ObjectDatabase $database database reference
     * @param File $file file to read
     * @param int $fstart byte offset to start reading
     * @param int $flast last byte to read (inclusive)
     * @see self::DoChunkedRead()
     */
    public static function ChunkedRead(ObjectDatabase $database, File $file, int $fstart, int $flast, bool $debugdl = false) : void
    {
        $rwsize = Config::GetInstance($database)->GetRWChunkSize();
        $fcsize = $file->GetChunkSize(); $align = ($fcsize !== null);
        $chunksize = self::GetChunkSize($rwsize, $fcsize);
        
        self::DoChunkedRead($file, $fstart, $flast, $chunksize, $align, $debugdl);
    }
    
    /**
     * Perform a chunked write to a file
     * @param ObjectDatabase $database reference
     * @param resource $handle input data handle
     * @param File $file write destination
     * @param int $wstart write offset
     * @return int number of bytes written
     * @see self::DoChunkedWrite()
     */
    public static function ChunkedWrite(ObjectDatabase $database, $handle, File $file, int $wstart) : int
    {
        $rwsize = Config::GetInstance($database)->GetRWChunkSize();
        $fcsize = $file->GetChunkSize(); $align = ($fcsize !== null);
        $chunksize = self::GetChunkSize($rwsize, $fcsize);
        
        return self::DoChunkedWrite($handle, $file, $wstart, $chunksize, $align);
    }    
}
