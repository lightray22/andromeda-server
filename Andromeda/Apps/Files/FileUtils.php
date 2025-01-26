<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Apps\Files\Items\File;

use Andromeda\Apps\Files\Storage\Exceptions\{FileReadFailedException, FileWriteFailedException};

/** Helper class for reading/writing streams and files via chunks */
class FileUtils
{    
    /**
     * Reads from the given stream (since fread may only return 8K)
     * @param resource $stream stream to read from
     * @param non-negative-int $bytes number of bytes to read
     * @param bool $strict if true, want exactly $bytes, else up to
     * @throws FileReadFailedException if reading the stream fails
     * @return string read data
     */
    public static function ReadStream($stream, int $bytes, bool $strict = true) : string
    {
        $byte = 0; $data = array();
        
        while (!feof($stream) && $byte < $bytes)
        {
            assert($bytes-$byte > 0); // guaranteed by while condition
            $read = fread($stream, $bytes-$byte);
            
            if (!is_string($read))
                throw new FileReadFailedException();
                
            $data[] = $read;
            $byte += strlen($read);
        }
        
        $data = implode($data);
        
        if ($strict && strlen($data) !== $bytes)
            throw new FileReadFailedException("read ".strlen($data).", wanted bytes");
        
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
            $piece = ($written > 0) ? substr($data, $written) : $data;
            
            $bytes = fwrite($stream, $piece);
            
            if ($bytes === false) 
                throw new FileWriteFailedException();
            
            $written += $bytes;
        }
        
        if ($written !== strlen($data))
            throw new FileWriteFailedException("wrote $written, wanted ".strlen($data));
    }

    /**
     * Performs a chunked File read, echoing output and counting bandwidth
     * @param File $file file to read from
     * @param non-negative-int $fstart first byte to read
     * @param non-negative-int $flast last byte to read (inclusive!)
     * @param int $chunksize read chunk size
     * @param bool $align if true, align reads to chunk size multiples
     * @param bool $debugdl if true, don't actually echo anything
     * @throws FileReadFailedException if reading the file fails
     */
    protected static function DoChunkedRead(File $file, int $fstart, int $flast, int $chunksize, bool $align, bool $debugdl = false) : void
    {
        for ($byte = $fstart; $byte <= $flast; )
        {
            if (connection_aborted() !== 0) break;
            
            // the next read should begin on a chunk boundary if aligned
            if (!$align) $nbyte = $byte + $chunksize;
            else $nbyte = (intdiv($byte, $chunksize) + 1) * $chunksize;
            
            $rlen = min($nbyte - $byte, $flast - $byte + 1);
            
            $data = $file->ReadBytes($byte, $rlen);
            
            if (strlen($data) !== $rlen)
                throw new FileReadFailedException();
                
            $file->CountBandwidth($rlen);
            $byte += $rlen;
            
            if (!$debugdl) { echo $data; flush(); } // TODO take a stream here too instead of echo directly - debugdl can be a dummy stream (ofc check benchmark)
        }
    }

    /**
     * Perform a chunked write to a file
     * @param resource $stream input data stream
     * @param File $file write destination
     * @param non-negative-int $wstart write offset
     * @param int $chunksize write chunksize
     * @param bool $align if true, align to chunksize multiples
     * @throws FileReadFailedException if reading input fails
     * @return non-negative-int number of bytes written
     */
    protected static function DoChunkedWrite($stream, File $file, int $wstart, int $chunksize, bool $align) : int
    {
        $wbyte = $wstart; 
        while (!feof($stream))
        {
            // the next write should begin on a chunk boundary if aligned
            if (!$align) $nbyte = $wbyte + $chunksize;
            else $nbyte = (intdiv($wbyte, $chunksize) + 1) * $chunksize;
            
            assert($nbyte-$wbyte >= 0); // guaranteed by intdiv math
            $data = self::ReadStream($stream, $nbyte-$wbyte, false);
            
            if (strlen($data) === 0) continue; // stream could be 0 bytes
            
            $file->WriteBytes($wbyte, $data);
            $wbyte += strlen($data);
        }
        
        assert($wbyte-$wstart >= 0); // wbyte only gets incremented
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
        
        return (int)$chunksize; // ceil math ensures int
    }
    
    /**
     * Peform a chunked file read to stdout
     * @param ObjectDatabase $database database reference
     * @param File $file file to read
     * @param non-negative-int $fstart byte offset to start reading
     * @param non-negative-int $flast last byte to read (inclusive)
     * @see self::DoChunkedRead()
     */
    public static function ChunkedRead(ObjectDatabase $database, File $file, int $fstart, int $flast, bool $debugdl = false) : void
    {
        $rwsize = Config::GetInstance($database)->GetRWChunkSize();
        $fcsize = $file->GetChunkSize();
        $align = ($fcsize !== null);
        $chunksize = self::GetChunkSize($rwsize, $fcsize);
        
        self::DoChunkedRead($file, $fstart, $flast, $chunksize, $align, $debugdl);
    }
    
    /**
     * Perform a chunked write to a file
     * @param ObjectDatabase $database reference
     * @param resource $stream input data stream
     * @param File $file write destination
     * @param non-negative-int $wstart write offset
     * @return non-negative-int number of bytes written
     * @see self::DoChunkedWrite()
     */
    public static function ChunkedWrite(ObjectDatabase $database, $stream, File $file, int $wstart) : int
    {
        $rwsize = Config::GetInstance($database)->GetRWChunkSize();
        $fcsize = $file->GetChunkSize();
        $align = ($fcsize !== null);
        $chunksize = self::GetChunkSize($rwsize, $fcsize);
        
        return self::DoChunkedWrite($stream, $file, $wstart, $chunksize, $align);
    }    
}
