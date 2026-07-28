<?php

namespace App\Entity\Enum;

/**
 * Terminal decision taken by a manager on an anomaly.
 *
 * This is the only part of the anomaly state that is persisted: the displayed
 * status is derived from it and from the linked tasks (see Anomaly::getStatus()).
 */
enum AnomalyResolution: string
{
    case TREATED = 'treated';
    case IGNORED = 'ignored';
}
