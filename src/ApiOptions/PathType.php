<?php

namespace LiturgicalCalendar\Components\ApiOptions;

/**
 * Enum of possible path types
 *
 * @package LiturgicalCalendar\Components\ApiOptions
 * @author John Roman Dorazio <priest@johnromanodorazio.com>
 */
enum PathType: string
{
    case BASE_PATH = 'basePath';
    case ALL_PATHS = 'allPaths';
}
