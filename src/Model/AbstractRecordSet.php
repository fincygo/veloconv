<?php
namespace App\Model;

/**
 *
 * @author petrics.lajos
 *        
 *        Record set of for a CSV file
 */
abstract class AbstractRecordSet implements \ArrayAccess, \IteratorAggregate
{
    /**
     * @var AbstractRecord[]
     */
    private $records;
    
    /**
     * @var array
     */
    private $headers;

    /**
     */
    public function __construct(?array $headers)
    {
        $this->headers = $headers;
    }
    
    public function getRecords()
    {
        return $this->records;
    }
    
    public function offsetGet($offset)
    {
        return $this->records[$offset];
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->records);
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->records);
    }

    public function offsetUnset($offset)
    {
        unset($this->records[$offset]);
    }

    public function offsetSet($offset, $value)
    {
        $this->records[$offset] = $value;
    }
    
    /**
     * @return array
     */
    public function getHeaders() : array
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }


    
    
}

