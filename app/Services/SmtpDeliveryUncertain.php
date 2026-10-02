<?php
declare(strict_types=1);
namespace App\Services;

/** DATA was submitted, but acceptance cannot be confirmed. Do not blindly retry. */
final class SmtpDeliveryUncertain extends \RuntimeException {}
