---
created: 2026-01-16T14:30
title: Split multi-class files and reorganize includes folder
area: refactoring
files:
  - phpcs.xml.dist:37
  - includes/*.php
---

## Problem

The codebase has multiple PHP files that contain more than one class per file, which violates the `Generic.Files.OneObjectStructurePerFile` WPCS rule. This rule is currently disabled in phpcs.xml.dist (line 37) to suppress violations.

Files known to have multiple classes include notification channels (e.g., multiple channel classes in one file) and REST controllers (grouped by domain).

Additionally, the `includes/` folder is a flat structure with all classes at the same level, making it harder to navigate as the codebase grows.

## Solution

1. Audit `includes/` to identify all files with multiple classes
2. Design a logical folder structure (e.g., `includes/rest/`, `includes/notifications/`, `includes/carddav/`, etc.)
3. Split multi-class files so each class has its own file
4. Update the autoloader in `functions.php` to handle the new structure
5. Remove the `Generic.Files.OneObjectStructurePerFile` exclusion from phpcs.xml.dist
6. Verify PHPCS passes with the rule enabled
