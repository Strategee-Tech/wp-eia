<?php

function isThumbnail($filename) {
    return preg_match('/-\d+x\d+\./', $filename);
}