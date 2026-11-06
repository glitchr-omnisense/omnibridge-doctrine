<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\User;

use Base\Database\Repository\Entities;
use Base\Repository\User\Complaints;

/*
 * What ComplaintRepository implements: the core's Complaints where the core has it (omnisense/omnibase
 * 5.x, its plan "omnibase agnostic", step E2'), Entities alone under omnibase 4.x, which this bridge
 * serves as well and which declares no Complaints. Gone once 4.x is no longer served (the plan's step S).
 */
if (interface_exists(Complaints::class)) {
    /** @internal ComplaintRepository's interface: the core's Complaints. */
    interface CoreComplaints extends Complaints
    {
    }
} else {
    /** @internal ComplaintRepository's interface under omnibase 4.x: Entities. */
    interface CoreComplaints extends Entities
    {
    }
}
