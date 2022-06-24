<?php
//=======================================================================================================================
function csvFieldName( $fieldname )
//-----------------------------------------------------------------------------------------------------------------------
//  The names in the csv header are converted to standard data table column names. 
//  The capital letters are converted to small letters, 
//  special codes, e.g. spaces, “ ”, brackets, “(”, ”)”  slashes “/” , “\”, dashes “-” are replaced by underscore, “_”. 
//  Instead of multiple underscores only one is used, e.g. “<space><dash><space>“ converted to a single underscore ”_”. 
//  No underscore at the end of a column name, 
//  e.g. the column name of the irap.csv header “Vehicle flow (AADT)” is converted to “vehicle_flow_aadt”.
//
{
    $ptrnUnderscore = "/['_','\-','\/','\\\',' ','(',')']+/i";
    return  strtolower( trim( preg_replace($pattern, '_', $fieldname ), '_' ));
}
//=======================================================================================================================
