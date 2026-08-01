<?php

namespace App\Http\Requests;

/**
 * Editing an alert puts it back on air, so it has to satisfy exactly the same
 * rules as a new one — including an end date still in the future.
 */
class UpdateAlertRequest extends StoreAlertRequest {}
