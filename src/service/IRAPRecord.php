<?php
namespace src\service;

use App\Model\AbstractRecord;

/**
 *
 * @author petrics.lajos
 *        
 */
class IRAPRecord extends AbstractRecord
{

    /**
     */
    public function __construct(array $fields)
    {
        parent::__construct($fields);
    }
}

