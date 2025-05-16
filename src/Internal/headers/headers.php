<?php

function escape($a){
    if($a == null){
        throw new Exception("Prameter null in escape");
    }
    return htmlspecialchars($a);
}