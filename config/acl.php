<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Separation of Duties (PRD §6.3)
    |--------------------------------------------------------------------------
    |
    | When enabled, a user cannot both author and approve/apply the same
    | controlled change — preventing conflicting authority over one record.
    |
    */

    'separation_of_duties' => env('ACL_SEPARATION_OF_DUTIES', true),

];
