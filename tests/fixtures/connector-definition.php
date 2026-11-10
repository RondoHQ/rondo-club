<?php
/** Minimal AI Connector registration contract; the plugin is not booted in this suite. */

namespace WPAgentAbilities\Abilities;

final class Definition {

	public const RISK_READ  = 'read';
	public const RISK_WRITE = 'write';

	public function __construct(
		public string $name,
		public string $label,
		public string $description,
		public string $risk,
		public mixed $permission_callback,
		public mixed $execute_callback,
		public array $input_schema = [],
		public array $output_schema = []
	) {}
}
