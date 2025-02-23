<?php
/**
 * ua-parser
 *
 * Copyright (c) 2011-2013 Dave Olsen, http://dmolsen.com
 * Copyright (c) 2013-2014 Lars Strojny, http://usrportage.de
 *
 * Released under the MIT license
 */
namespace UAParser\Result;

class OperatingSystem extends AbstractVersionedSoftware
{
    /** @var string|null */
    public $major;

    /** @var string|null */
    public $minor;

    /** @var string|null */
    public $patch;

    /** @var string|null */
    public $patchMinor;

    public function toVersion(): string
    {
        return $this->formatVersion($this->major, $this->minor, $this->patch, $this->patchMinor);
    }
}
