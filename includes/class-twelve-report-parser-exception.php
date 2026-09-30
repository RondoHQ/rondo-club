<?php
/**
 * Exception thrown when a Twelve report cannot be parsed.
 *
 * @package Rondo
 */

namespace Rondo\Twelve;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportParserException extends \RuntimeException {}
