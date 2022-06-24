<?php
namespace App\Model;

/**
 *
 * @author petrics.lajos
 *        
 *        One record for a CSV file
 */
abstract class AbstractRecord
{
    /**
     * @var array
     */
    protected $fields;
    
    /**
     */
    public function __construct(?array $fields)
    {
        if (null !== $fields) {
            $this->records = $fields;
        }
    }
    
    protected function setInitialFields(array $fields)
    {
        $this->records = $fields;
    }
    
    public function getFieldvalue($name)
    {
        $value = null;
        if (array_key_exists($name, $this->fields)) {
            $value = $this->fields['name'];
        }
        // TODO: else throw log error
        
        return $value;
    }
    
    public function setFieldvalue($name, $value) : self
    {
        if (array_key_exists($name, $this->fields)) {
            $this->fields['name'] = $value;
        }
        
        // TODO: throw log error
        
        return $this;
    }
}

