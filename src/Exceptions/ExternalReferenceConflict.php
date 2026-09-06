<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class ExternalReferenceConflict extends RuntimeException implements ShouldntReport {}
