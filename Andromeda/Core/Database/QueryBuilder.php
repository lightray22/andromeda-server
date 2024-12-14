<?php declare(strict_types=1); namespace Andromeda\Core\Database; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;

/** Minimalistic class for building prepared post-FROM SQL query strings */
class QueryBuilder
{
    /** 
     * variables to be substituted in the query
     * @var array<string, scalar>
     */
    private array $params = array();

    /** @return array<string, scalar> */
    public function GetParams() : array { return $this->params; }
    
    /** Returns the compiled query as a string */
    public function GetText() : string 
    { 
        $query = $this->fromalias ?? "";

        foreach ($this->joins as $joinstr)
            $query .= " JOIN $joinstr";
        
        if ($this->where !== null) 
            $query .= " WHERE $this->where";

        if ($this->orderby !== null) 
        {
            $query .= " ORDER BY \"$this->orderby\"";
            if ($this->orderdesc) $query .= " DESC"; // default is ASC
        }
        
        if ($this->limit !== null) 
            $query .= " LIMIT ".$this->limit;
        
        if ($this->offset !== null) 
            $query .= " OFFSET ".$this->offset;
        
        return trim($query);
    }
    
    public function __toString() : string { return $this->GetText(); }
    
    private ?string $fromalias = null;
    /** @var list<string> */
    private array $joins = array();
    private ?string $where = null;
    private ?string $orderby = null;
    private bool $orderdesc = false;
    private ?int $limit = null;
    private ?int $offset = null;
    
    /** The current index for the param array */
    private int $paramIdx = 0;

    /**
     * Adds the given value to the internal param array
     * @param scalar $val the actual data value
     * @return string the placeholder to go in the query
     */
    protected function AddParam($val) : string
    {
        $idx = "d".$this->paramIdx++;
        $this->params[$idx] = $val;
        return ':'.$idx;
    }
    
    /** Returns the given string with escaped SQL wildcard characters */
    public static function EscapeWildcards(string $query) : string
    {
        return Utilities::escape_all($query,['_','%']);
    }
    
    /**
     * Returns a string asserting the given column is null
     * @param bool $quotes if true, surround key with ""
     */
    public function IsNull(string $key, bool $quotes = true) : string 
    { 
        return ($quotes ? "\"$key\"" : $key)." IS NULL";
    }
    
    /**
     * Returns a string comparing the given column to a value using LIKE
     * @param string $key the name of the column to compare
     * @param string $val the value to check for (NOTE use EscapeWildcards if using hasMatch!)
     * @param bool $hasMatch if true, the string manages its own SQL wildcard characters else use %$val%
     * @return string the built string
     */
    public function Like(string $key, string $val, bool $hasMatch = false) : string 
    {
        if (!$hasMatch) $val = '%'.static::EscapeWildcards($val).'%';
        return "\"$key\" LIKE ".$this->AddParam($val). " ESCAPE '\\'"; 
    }
    
    /** 
     * Returns a query string asserting the given column is less than the given value 
     * @param string $key the name of the column to compare
     * @param scalar $val the column value to compare
     */
    public function LessThan(string $key, $val) : string { 
        return "\"$key\" < ".$this->AddParam($val); }
    
    /** 
     * Returns a query string asserting the given column is less or equal to the given value 
     * @param string $key the name of the column to compare
     * @param scalar $val the column value to compare
     */
    public function LessThanEquals(string $key, $val) : string { 
        return "\"$key\" <= ".$this->AddParam($val); }
    
    /**
     * Returns a query string asserting the given column is greater than the given value 
     * @param string $key the name of the column to compare
     * @param scalar $val the column value to compare
     */
    public function GreaterThan(string $key, $val) : string { 
        return "\"$key\" > ".$this->AddParam($val); }
    
    /** 
     * Returns a query string asserting the given column is greater than or equal to the given value 
     * @param string $key the name of the column to compare
     * @param scalar $val the column value to compare
     */
    public function GreaterThanEquals(string $key, $val) : string { 
        return "\"$key\" >= ".$this->AddParam($val); }
    
    /** Returns a query string asserting the given column is "true" (greater than zero) */
    public function IsTrue(string $key) : string {
        return $this->GreaterThan($key,0); }
    
    /** 
     * Returns a query string asserting the given column is equal to the given value 
     * @param string $key the name of the column to compare
     * @param ?scalar $val the column value to compare
     * @param bool $quotes if true, surround key with ""
     */
    public function Equals(string $key, $val, bool $quotes = true) : string 
    { 
        if ($val === null) return $this->IsNull($key, $quotes);
        return ($quotes ? "\"$key\"" : $key)." = ".$this->AddParam($val);
    }
    
    /**
     * Returns a query string asserting the given column is not equal to the given value 
     * @param string $key the name of the column to compare
     * @param ?scalar $val the column value to compare
     */
    public function NotEquals(string $key, $val) : string 
    { 
        if ($val === null) return $this->Not($this->IsNull($key));
        return "\"$key\" <> ".$this->AddParam($val);
    }
    
    /**
     * Syntactic sugar function to check many OR conditions at once
     * @param string $key the column to compare against
     * @param array<?scalar> $vals array of possible values for the column
     * @param bool $quotes if true, surround key with ""
     * @return string the built query string
     */
    public function ManyEqualsOr(string $key, array $vals, bool $quotes = true) : string
    {
        return $this->Or(...array_map(function($val)use($key,$quotes){
            return $this->Equals($key,$val,$quotes); },$vals));
    }
    
    /**
     * Syntactic sugar function to check many AND conditions at once
     * @param array<string, ?scalar> $pairs associative array mapping column names to their desired values
     * @return string the built query string
     */
    public function ManyEqualsAnd(array $pairs) : string
    {
        $retval = array(); foreach($pairs as $key=>$val){
            $retval[] = $this->Equals($key, $val); }
        return $this->And(...$retval);
    }
    
    /** Returns a query string that inverts the logic of the given query */
    public function Not(string $arg) : string { return "(NOT $arg)"; }
    
    /** Returns a query string that combines the given arguments using OR */
    public function Or(string ...$args) : string { return "(".implode(' OR ',$args).")"; }
    
    /** Returns a query string that combines the given arguments using AND */
    public function And(string ...$args) : string { return "(".implode(' AND ',$args).")"; }

    /** 
     * Assigns/adds a WHERE clause to the query
     * if null, resets - if called > once, uses AND 
     * @return $this
     */
    public function Where(?string $where) : self 
    {
        if ($where === null)
        {
            $this->where = null;
            $this->params = array();
        }
        else if ($this->where !== null)
            $this->where = $this->And($this->where, $where);
        else $this->where = $where; 
        return $this;
    }
    
    /** Returns the current WHERE string */
    public function GetWhere() : ?string { return $this->where; }
        
    /** 
     * Assigns an ORDER BY clause to the query, optionally descending, null to reset
     * @return $this
     */
    public function OrderBy(?string $orderby, ?bool $desc = null) : self 
    { 
        $this->orderby = $orderby; 
        $this->orderdesc = ($desc === true); 
        return $this; 
    }
    
    /** Returns the current ORDER BY key or null */
    public function GetOrderBy() : ?string { return $this->orderby; }
    
    /** Returns true if the order is descending */
    public function GetOrderDesc() : bool { return $this->orderdesc; }
    
    /** 
     * Assigns a LIMIT clause to the query, null to reset
     * @param NULL|non-negative-int $limit
     * @return $this
     */
    public function Limit(?int $limit) : self 
    {
        $this->limit = $limit; return $this; 
    }
    
    /** 
     * Assigns an OFFSET clause to the query (use with LIMIT), null to reset
     * @param NULL|non-negative-int $offset
     * @return $this
     */
    public function Offset(?int $offset) : self 
    {
        $this->offset = $offset; return $this; 
    }

    /** Returns the set query limit */
    public function GetLimit() : ?int { return $this->limit; }
    
    /** Returns the set query offset */
    public function GetOffset() : ?int { return $this->offset; }
    
    /**
     * Adds a JOIN clause to the query (can have > 1)
     * @param ObjectDatabase $database reference to the database
     * @param class-string<BaseObject> $joinclass the class of the objects that join us to the destination class
     * @param string $joinprop the column name of the join table that matches the destprop
     * @param class-string<BaseObject> $destclass the class of the destination object
     * @param string $destprop the column name of the destination object that matches the joinprop
     * @param bool $quotes if true, surround key with ""
     * @return $this
     */
    public function Join(ObjectDatabase $database, string $joinclass, string $joinprop, string $destclass, string $destprop, bool $quotes = true) : self
    {
        $joinclass = $database->GetClassTableName($joinclass); 
        $destclass = $database->GetClassTableName($destclass);

        if ($quotes)
        {
            $joinprop = "\"$joinprop\"";
            $destprop = "\"$destprop\"";
        }
        
        $this->joins[] = "$joinclass ON $joinclass.$joinprop = $destclass.$destprop"; return $this;
    }

    /**
     * Performs a self join on a table (selects an alias table and adds to the WHERE query)
     * @param ObjectDatabase $database database reference
     * @param class-string<BaseObject> $joinclass the table to join to itself 
     * @param string $prop1 the column to match to prop2
     * @param string $prop2 the column to match to prop1
     * @param bool $quotes if true, surround key with ""
     * @return $this
     */
    public function SelfJoinWhere(ObjectDatabase $database, string $joinclass, string $prop1, string $prop2, bool $quotes = true) : self
    {
        $jointable = $database->GetClassTableName($joinclass); 
        
        $this->fromalias = ", $jointable _tmptable"; // TODO FUTURE not likely to work correctly now - at least make this (fromalias) more general

        if ($quotes)
        {
            $prop1 = "\"$prop1\"";
            $prop2 = "\"$prop2\"";
        }
        
        return $this->Where("$jointable.$prop1 = _tmptable.$prop2");
    }
}

