<?php
/** Minimal AI Connector registry used to capture Rondo's registrations. */

namespace WPAgentAbilities\Abilities;

require_once __DIR__ . '/connector-definition.php';

final class Registry {

	public array $definitions = [];

	public function add( Definition $definition ): void {
		$this->definitions[ $definition->name ] = $definition;
	}
}
