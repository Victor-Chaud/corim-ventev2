<?php

foreach (glob(__DIR__.'/modules/*.php') ?: [] as $file) {
    require $file;
}
