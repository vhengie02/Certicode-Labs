<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Who runs Certicode
    |--------------------------------------------------------------------------
    |
    | Named on the Privacy Policy and Terms as the personal information
    | controller, and as the person to contact about personal data.
    |
    */

    'operator_name' => env('LEGAL_OPERATOR_NAME', 'John Vhengie Booc'),

    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'lyralabs04@gmail.com'),

    // Optional. Shown under the contact email when set.
    'contact_address' => env('LEGAL_CONTACT_ADDRESS'),

    /*
    |--------------------------------------------------------------------------
    | Policy versions
    |--------------------------------------------------------------------------
    |
    | Raising terms_version asks every user to accept the Terms and Privacy
    | Policy again. Raising monitoring_version asks every student to agree to
    | camera and activity monitoring again before their next lab. Raise them
    | when a change affects what people agreed to, such as a new use of their
    | data. Fixing a typo doesn't need a new version.
    |
    */

    'terms_version' => '2026-11-06',

    'monitoring_version' => '2026-11-06',

    'effective_date' => '2026-11-06',

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    |
    | Off only in the test suite, where most tests create users directly and
    | aren't about consent. LegalConsentTest turns it back on.
    |
    */

    'enforce' => (bool) env('LEGAL_ENFORCE', true),

];
