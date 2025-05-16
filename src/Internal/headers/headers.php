<?php

function e($a){
    if($a == null){
        throw new Exception("Prameter null");
    }
    return htmlspecialchars($a);
}