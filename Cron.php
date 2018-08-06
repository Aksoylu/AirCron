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

session_start();
 

$date = date("d.m.y H:i:s");    //Server Date as String
$time = calc_date($date);
$file = fopen('Cron_base.txt', 'rw');
$text = fread($file, filesize('Cron_base.txt'));
fclose($file);
$text = str_replace("\r\n", "", $text);

$cronbase = json_decode($text,true);

$timing = $cronbase[0][0];
$crons = $cronbase[1];


foreach($crons as $task)
{
    
    $id = $task["id"];
    $page = $task["page"];
    $request = $task["request"];
    $datas = "";
    
    if(isset($task["data"]))
    {
        $datas = $task["data"];
    }
    
    $timearray = array();
        
    if(isset($task["interval"]))
    {
        $file = fopen('Cron_base.txt', 'rw');
        $text = fread($file, filesize('Cron_base.txt'));
        fclose($file);
        $text = str_replace("\r\n", "", $text);

        $cronbase = json_decode($text,true);

        $timing = $cronbase[0][0];
        $crons = $cronbase[1];
   
        $timearray[0] = $task["interval"];      //interval
        $timearray[1] = $timing[$id];           //last task running time as digit
        $timearray[2] = $time;                  //now time as digit 

        $differance = $timearray[1] - $time;
       
       
        if($differance <= $task["interval"])
        {
            //if multiple section setted true, we will run the task "latency count" times
            if($task["multiple"] == "true")
            {

                //calculate latency count
                $lcount = (int)-(($timearray[2] - $timearray[1])/ $timearray[0]);

                for($i=0;$i<$lcount;$i++)
                {
                    //run this task     
                    run_task($page,$request,$datas,$timearray);

                }

                //Loop may take time so we renew system time here again.
                $time = calc_date(date("d.m.y H:i:s"));

                
                //Update tasks last run time in Cron_base.txt with renewed $time    
                update_interval($id,$time,$cronbase);

            }
            else
            {
               
                //run this task
                run_task($page,$request,$datas,$timearray);
                
                //update tasks last run time in Cron_base.txt
                update_interval($id,$time,$cronbase);
                
            }
            
        }
        
       
        
    }
    else if (isset($task["date"]))
    {
        
        $timearray[0] = $task["date"]; //fixed running time as [Day.Month.Year Second:Minute:Hour]
        $timearray[1] = $time;         //Now time as digit 
        
        if(calc_date($date) == calc_date($task["date"]) || calc_date($task["date"]) < calc_date($date))
        {
            //run this task
            run_task($page,$request,$datas,$timearray);
            
            //remove this task from cron_base.txt
            kill_task($id,"date",$cronbase);
            
           
        }
        
    }
    else
    {
        continue;
    }
    
    
}



//This function updates interval type operating task's last running time as digit
function update_interval($task_id,$value,$cronbase)
{
$json[0] = $cronbase[0][0];
$json[1] = $cronbase[1];

$json[0][$task_id] = $value;

$file = fopen('Cron_base.txt', 'w');
fwrite($file,json_encode($json));
fclose($file);
 
    
}

//This function removes date type operating task from Cron_base.txt after executing
 function kill_task($task_id,$type,$cronbase)
{
    if($type == "date")
    {
        $json[0] = $cronbase[0][0];
        $json[1] = $cronbase[1];

        unset($json[1][$task_id]);

   
        $file = fopen('Cron_base.txt', 'w');
        fwrite($file,json_encode($json));
        fclose($file);
    }
    
}

//This function run a task from cron_base.txt 
function run_task($url,$request,$param,$timearray)
{
    
    $postdata = array();
    $contents = "";
    if($request == "POST")
    {
               
        if(strpos($param,','))
        {
                  
            $i = 0;
            $params = explode($param,',');
            foreach($params as $parameter)
            {
                $id = "POST".$i;
                $parameter = interpreter($parameter,$timearray);
                $postdata[$id]=$parameter;
                $i++;
            }
        }
        else
        {
            $parameter = interpreter($param,$timearray);
            $postdata["POST0"]=$parameter;
        }
        
       
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($postdata)
            )
        );
        
        $context  = stream_context_create($options);
        $contents = file_get_contents($url, false, $context);
        
    }
    else if ($request == "GET")
    {
        
        $getdata = "";
        $i=0;
        
        if(strpos($param,','))
        {
            $params = explode($param,',');

            foreach($params as $parameter)
            {
                $parameter= interpreter($parameter,$timearray);
                $getdata .= "GET".$i."=".urlencode($parameter)."&";
                $i++;
            }
               
        }
        else
        {
             $parameter = interpreter($param,$timearray);
             $getdata .= "GET0=". urlencode($parameter);
             
        }
       
        $contents = file_get_contents($url."?".$getdata);
      
    }
    else if($request == "INVOKE")
    {
        $contents = file_get_contents($url);   
    }
     echo $contents;    //Delete this if you don't want to see task running result
    
}

//This function interprets variables and returns actual values
function interpreter($text,$timearray)
{
 
      
        $pattern_ses = "/(ses)\(([A-z0-9\-_\.]*)\)/";
        $pattern_cook = "/(cook)\(([A-z0-9\-_\.]*)\)/";
        $pattern_clock = "/(clock)\(([A-z0-9\-_\.:]*)\)/";
        if(preg_match($pattern_ses, $text,$result))
        {
            
            return $_SESSION[$result[2]];
            
        }
        else if(preg_match($pattern_cook, $text,$result)) 
        {
            return $_COOKIE[$result[2]];
        }
        else if(preg_match($pattern_clock,$text,$result))
        {
            
        //For Interval Type Operating
        //$timearray[0] = interval
        //$timearray[1] = last task running time as digit
        //$timearray[2] = now time as digit
        
        //For Date Type Operating
        //$timearray[0] = fixed running time as [D.M.Y S:M:H]
        //$timearray[1] = now time as digit 
            
            if($result[2] == "runtime:dmy_smh")
            {
                $date = date("d.m.y H:i:s"); 
                //returns tasks operating time as [Day.Monty.Year Hour:Minute:Second] (For Date & Interval Type Operating)
                if(sizeof($timearray) == 3) //Interval Type Operating
                {   
                    return $date;
                    
                }
                else if(sizeof($timerray) == 2) //Date Type Operating
                {   
                    return $date;
                    
                }
                return $date;
                
            }
            else if($result[2] == "runtime:digit")
            {
                //returns task operating time as converted into digit (For Date & Interval Type Operating)
                if(sizeof($timearray) == 3)
                {
                    return $timearray[2];
                    
                }
                else if(sizeof($timearray) == 2)
                {
                    return $timearray[1];
                    
                }
                
                return $time;
                
            }
            else if($result[2] == "latency:dmy_smh")
            {
                //returns tasks operating latency as [Day.Monty.Year Second:Minute:Hour] (For Date & Interval Type Operating) 
                
                if(sizeof($timearray) == 3)  
                {
                    return calc_second($timearray[0] - ($timearray[2] - $timearray[1])) ;
                    
                }
                else if (sizeof($timearray) == 2)
                {
                    return calc_second($timearray[1] - calc_date($timearray[0]));
                    
                }
                return date;
                
            }
            else if($result[2] == "latency:digit")
            {
                //returns task operating latency as converted into digit (For Date & Interval Type Operating)
                 if(sizeof($timearray) == 3)  
                {  
                    return $timearray[0] - ($timearray[2] - $timearray[1]) ;
                    
                }
                else if (sizeof($timearray) == 2)
                {
                    return $timearray[1] - calc_date($timearray[0]);
                    
                }
               
            }
            else if($result[2]== "lacenty:count")
            {
                //returns number of times the task cannot be repeated due to delay (For Interaval Type Operating)
                if(sizeof($timearray)==3)
                {                  
                    return (int)-(($timearray[2] - $timearray[1])/ $timearray[0]);
                    
                }
               
            }
             
        }
        
    
}

//This function converts secons to dates as [Day.Month.Year Second:Minute:Day]
function calc_second($digit)
{
    $y =  0;
    $m =  0;
    $d =  0;
    $h =  0;
    $mi = 0;
    $s =  0;
    
    if($digit >= 31104000)
    {
       $y = $digit / 31104000;
       $digit = $digit - (int)$y * 31104000;
        
    }
    
    if($digit >= 2592000)
    {
        $m = $digit / 2592000;
        $digit = $digit - (int)$m * 2592000;
        
    }
    
    if($digit >= 86400)
    {
        $d = $digit / 86400;
        $digit = $digit - (int)$d * 86400;
        
    }
        
    if($digit >= 3600)
    {
        $h = $digit / 3600;
        $digit = $digit - (int)$h * 3600;
    }
    
    if($digit >= 60)
    {
        $mi = $digit / 60;
        $digit = $digit - (int)$mi * 60;
    }
    
    if($digit >= 1)
    {
        $s = $digit;
        
    }
        
    
    
    return (int)$d.".".(int)$m.".".(int)$y." ".(int)$h.":".(int)$mi.":".(int)$s;
  
}

//This function converts dates to second as digit
function calc_date($text)
{
    $split_space = explode(" ",$text);
     
    $split_dot = explode(".",$split_space[0]);
    
    $day = $split_dot[0] + $split_dot[1]* 30 + $split_dot[2] * 365;
    
    $split_ = explode(":",$split_space[1]);
    
    
    $sec =  $day * 24 * 60 * 60 + $split_[0] * 60  * 60 + $split_[1] * 60 + $split_[2];
    
    return $sec;
}




?>