<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Exceptions;

use Exception;

class WrongBitrix24ConfigurationException extends Exception
{
    protected ?string $advice;

    /**
     * WrongBitrix24ConfigurationException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     * @param string|null     $advice
     */
    public function __construct($message = "", $code = 0, \Throwable $previous = null, string $advice = null)
    {
        parent::__construct($message, $code, $previous);
        $this->advice = $advice;
    }

    /**
     * @return string|null
     */
    public function getAdvice(): ?string
    {
        return $this->advice;
    }
}