<?php
/**
 * Schema PHP Template Framework
 *
 * @version  1.1.6
 * @link     https://schema.io
 * @license  http://opensource.org/licenses/mit MIT
 */

namespace Schema;

require __DIR__.'/index.php';

/* -----------------------------------------------------
 * Setup Request
 * -------------------------------------------------- */

Request::setup();

/* -----------------------------------------------------
 * Dispatch The Request
 * -------------------------------------------------- */

Request::dispatch();
