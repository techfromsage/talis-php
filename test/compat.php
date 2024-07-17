<?php

namespace PHPUnit\Framework\MockObject {
    if (!interface_exists(\PHPUnit\Framework\MockObject\MockObject::class)) {
        interface MockObject extends \PHPUnit_Framework_MockObject_MockObject
        {
        }
    }
}
