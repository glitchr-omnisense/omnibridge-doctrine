<?php

namespace Omnibridge\Doctrine\Tests\Fixtures\Schema;

enum State: string
{
    case Draft = 'draft';
    case Published = 'published';
}
