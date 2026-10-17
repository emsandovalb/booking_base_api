<?php

namespace App\Services;

/**
 * Internal to BookingScheduler: the slot was taken between the availability
 * check and the insert. Thrown inside the transaction so it rolls back.
 */
final class SlotTakenException extends \RuntimeException {}
