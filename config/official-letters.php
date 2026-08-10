<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Organization reference prefix
    |--------------------------------------------------------------------------
    */
    'org_prefix' => 'REF NGO',

    /*
    |--------------------------------------------------------------------------
    | Letter department codes
    |--------------------------------------------------------------------------
    |
    | Independent of HR departments. Add new codes here to expand the list.
    |
    */
    'departments' => [
        'ADM' => 'Administration',
        'HR' => 'Human Resources',
        'MGT' => 'Management',
        'PR' => 'Public Relations & Advocacy',
        'FUN' => 'Fundraising',
        'FIN' => 'Finance',
        'PRO' => 'Programs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reference number format template
    |--------------------------------------------------------------------------
    |
    | Tokens: {prefix}, {department}, {mmm}, {yy}, {day}, {sequence}
    |
    */
    'reference_format' => '{prefix}/{department}/{mmm}-{yy}/{day}-{sequence}',

    /*
    |--------------------------------------------------------------------------
    | Letterhead footer (PDF)
    |--------------------------------------------------------------------------
    */
    'letterhead' => [
        'organization_name' => 'Adilisha Child and Youth Development organization',
        'postal_address' => 'P.O. Box 11098',
        'city_country' => 'Mwanza Tanzania East Africa',
        'physical_address' => "Physical Address: Plot No.99 Block 'C' Ghana Street,\nIlemela, MWANZA",
        'org_reg_no' => 'ORG Reg No 00NGO/R2/000358',
        'website' => 'www.adilisha.or.tz',
        'email' => 'info@adilisha.or.tz',
        'phones' => '+(255) 28 2561724 , +(255) 757762231',
    ],

    /*
    |--------------------------------------------------------------------------
    | Salutation options
    |--------------------------------------------------------------------------
    */
    'salutations' => [
        'Dear Sir/Madam,',
        'Dear Sir,',
        'Dear Madam,',
        'Dear Sirs,',
        'Dear All Staffs,',
        'To Whom It May Concern,',
    ],

    /*
    |--------------------------------------------------------------------------
    | Closing options
    |--------------------------------------------------------------------------
    */
    'closings' => [
        'In service,',
        'Yours faithfully,',
        'Yours sincerely,',
        'Best regards,',
        'Kind regards,',
    ],

];
