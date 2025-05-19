<?php

function escape($a){
    if($a == null && !is_int($a)){
        throw new Exception("Prameter null in escape");
    }
    return htmlspecialchars($a);
}