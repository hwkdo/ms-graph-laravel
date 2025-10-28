<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

use App\Models\User;
use Illuminate\Support\Carbon;

interface MsGraphOutOfOfficeTemplateServiceInterface
{
    public function getTemplate(User $user, User $colleague, ?Carbon $limit = null, ?string $notice = null): string;
}
