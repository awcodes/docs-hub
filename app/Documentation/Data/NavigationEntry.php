<?php

declare(strict_types=1);

namespace App\Documentation\Data;

/**
 * One item in a version's navigation tree.
 *
 * A manifest entry is either a page reference or a presentational group, and
 * nothing else. Modelling that as two types rather than one array
 * shape is what lets the renderer stop asking which it is holding.
 */
interface NavigationEntry {}
