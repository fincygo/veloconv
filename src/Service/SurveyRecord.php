<?php
namespace App\Service;

use App\Model\AbstractRecord;

/**
 *
 * @author petrics.lajos
 *        
 */
class SurveyRecord extends AbstractRecord
{

    /**
     */
    public function __construct(array $fields)
    {
        parent::__construct($fields);
    }
}

