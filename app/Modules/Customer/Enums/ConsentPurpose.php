<?php

namespace App\Modules\Customer\Enums;

enum ConsentPurpose: string
{
    case ServiceContact = 'service_contact';
    case Marketing = 'marketing';
}
