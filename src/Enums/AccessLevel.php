<?php

namespace Laraditz\Jaga\Enums;

enum AccessLevel: string
{
    case Restricted = 'restricted'; // authenticated + explicit permission required (default)
    case Auth       = 'auth';       // any authenticated user, no permission check
    case Public     = 'public';     // no authentication required
}
