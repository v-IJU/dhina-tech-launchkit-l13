<?php

declare(strict_types=1);

namespace App\Modules\Services\Requests;

/**
 * Same rules as creating. Unique rules already ignore the record being edited.
 * Override rules() here when editing needs different validation.
 */
class UpdateServiceRequest extends StoreServiceRequest {}
