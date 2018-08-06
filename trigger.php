<?php
/*
 =========================================================
 * AirCron Lightweight CronJob System - v1.0
 =========================================================
 
 * Product Page:  http://umitaksoylu.space/aircron/
 * Copyright 2018 Ümit AKSOYLU (http://www.umitaksoylu.space)
 * Licensed under MIT (https://github.com/Aksoylu/AirCron/upload/master/licence.md)
 
 =========================================================

 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 */

//We don't run Cron.php for every time because of to improving performance.
//Its %20 chance to run but dont worry. It doesnt matter if you dont make a punctual job
//If you want run cron in every page refresh, set $chance = 1

$chance = 1;
if(rand(1,$chance) == 1)
{
   
    require("Cron.php");
}
    
?>

