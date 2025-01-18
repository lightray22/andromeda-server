<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\{ApiPackage, Config, RunContext};
use Andromeda\Core\Database\DBStats;
use Andromeda\Core\IOFormat\Output;

class MetricsHandler
{
    /** performance metrics for initialization */
    private DBStats $init_stats;
    
    /** total request performance metrics */
    private DBStats $total_stats;
    
    /** Creates a new MetricsHandler and starts timing */
    public function __construct()
    {
        $this->total_stats = new DBStats();
    }
    
    /** Creates and returns a new DBStats to be used for init_stats */
    public function GetInitStats() : DBStats
    {
        return $this->init_stats = new DBStats();
    }

    /**
     * Compiles performance metrics and adds them to the given output, and logs
     * @param ApiPackage $apipack API package with database
     * @param RunContext $context the run context to save stats from
     * @param Output $output the output object to add metrics to
     * @param bool $isError if true, the output is an error response
     * @throws Exceptions\MetricsTransactionException if already in a db transaction
     */
    public function SaveMetrics(ApiPackage $apipack, RunContext $context, Output $output, bool $isError = false) : void
    {
        try // request should still succeed if this fails
        {
            $database = $apipack->GetDatabase();
            // want to re-use DB, saving must be in its own transaction
            if ($database->GetInternal()->inTransaction())
                throw new Exceptions\MetricsTransactionException();
            
            $total_stats = clone $this->total_stats;
            $total_stats->stopTiming(false); // no autoloader, overlaps

            $total_stats->Add($this->init_stats, false);
            $total_stats->Add($context->GetActionMetrics(), false);
            if ($context->HasCommitMetrics())
                $total_stats->Add($context->GetCommitMetrics(), false);
            
            $metrics = MetricsLog::Create($apipack->GetMetricsLevel(), 
                $database, $this->init_stats, $context, $total_stats);

            if (!$apipack->isCommitRollback() && !$database->isReadOnly()){
                $metrics->Save(); $database->commit(); }

            if ($apipack->GetMetricsLevel(true) !== 0)
                $output->SetMetrics($metrics->GetClientObject($isError));
        }
        catch (\Throwable $e)
        {
            if ($apipack->GetDebugLevel() >= Config::ERRLOG_DETAILS) throw $e;
            else $apipack->GetErrorManager()->LogException($e, false);
        }
    }
}