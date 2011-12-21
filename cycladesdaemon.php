
<?php
include "php_serial.class.php";

$serial = new phpSerial;

   $dbhandle = sqlite_open('db/test.db', 0666, $error);
   if (!$dbhandle) die ($error);

function startSerial()
{

   global $path, $baud, $serial;

   $serial->deviceSet($path);
   $serial->confBaudRate($baud);
   $serial->deviceOpen();


   // To write into
   // $serial->sendMessage("Hello !");
   // Or to read from
   // $read = $serial->readPort();

   // If you want to change the configuration, the device must be closed
  // $serial->deviceClose();
}


Print ("Daemon for strip $argv[1]\n");
$strip = $argv[1];

$result = sqlite_query($dbhandle, "SELECT path, baud, outlets, login, pass, type FROM strips WHERE number='$strip'");
$row = sqlite_fetch_array($result, SQLITE_NUM);
$path = $row['0'];
$baud = $row['1'];
$outlets = $row['2'];
$login = $row['3'];
$pass = $row['4'];
$type = $row['5'];

print ("Device=$path, Baud=$baud, Outlets=$outlets\n");

if ($type != "1") {
  print ("This daemon is for cyclades units only.\n");
  die;
}

startSerial();

$j = 1;
$l = 0;




// Main loop

while ($j != 0) {

   $serial->sendMessage("\n");
   $read = $serial->readPort();

if(stristr($read, 'sername') == TRUE) {
       print ("--> Sending login info.\n");
       $serial->sendMessage("admin\n"); 
       $serial->sendMessage("pm8\n");
    }



if(stristr($read, 'pm>') == TRUE) {

    $result = sqlite_query($dbhandle, "SELECT port, change, Id FROM commands WHERE strip = $strip");
    $row = sqlite_fetch_array($result, SQLITE_NUM) ;

   if ($row['2'] > 0) {

    print ("--> JOB from DB : Port=" . $row['0'] . " Change=" . $row['1'] . " ID=" . $row['2'] . "\n");

       if ($row['1'] == '1') {

         $serial->sendMessage("on $row[0] \n");
         print ("--> Turned ON port " . $row['0'] );

      // Write status back into database of devices
         $stm = "UPDATE devices SET status='1' WHERE stripnumber='$strip' AND port='$row[0]'";
         $ok = sqlite_exec($dbhandle, $stm, $error);
           if (!$ok)
              print ("Cannot execute query: $error");

      // Delete job from the command
        $id = $row['2'];
        $stm = "DELETE FROM commands WHERE Id='$id'";
        $ok = sqlite_exec($dbhandle, $stm, $error);
           if (!$ok)
              print ("Cannot execute query: $error");
        print ("--> Deleted job ID" . $id . "\n");



       }
 

       if ($row['1'] == '0') {
        $serial->sendMessage("off $row[0] \n");
        print (" Turned OFF port " .  $row['0']);
        $stm = "UPDATE devices SET status='0' WHERE stripnumber='$strip' AND port='$row[0]'";
        $ok = sqlite_exec($dbhandle, $stm, $error);
          if (!$ok)
             print ("Cannot execute query: $error");

       $id = $row['2'];
       $stm = "DELETE FROM commands WHERE Id='$id'";
        $ok = sqlite_exec($dbhandle, $stm, $error);
           if (!$ok)
              print ("Cannot execute query: $error");
        print ("--> Deleted job ID" . $id . "\n");


       }

      print ($read);
   }
} 


if(stristr($read, 'IPDU') == TRUE) {
//IPDU #1: True RMS current: 0.0A. Maximum current: 0.4A
// THIS NEEDS UPDATING TO BRING IN THE # OF THE IPDU

   // Find IPDU number
   $numberstart = (strpos($read, '#', 1)); 
   $numberend = (strpos($read, ':', 1)); 

   // Find true RMS Current value
   $truestart = (strpos($read, ':', ($numberend+2))); 
   $trueend = (strpos($read, 'A.', ($numberend+2))); 

   // Find Maximum current value
   $maxstart = (strpos($read, 't:', 44)); 
   $maxend = (strpos($read, 'A', 44)); 

   $ipdu = substr($read, ($numberstart+1), ($numberend-$numberstart-1));

   $truerms = substr($read, ($truestart+1), ($trueend-$truestart-1));

   $maxrms = substr($read, ($maxstart+2), ($maxend-$maxstart-1));

   print ("\n\n--ipdu=$ipdu--truerms=$truerms--maxrms=$maxrms--\n");


      $stm = "UPDATE current SET average='$truerms', peak='$maxrms' WHERE strip='$strip' AND substrip='$ipdu'";
      $ok = sqlite_exec($dbhandle, $stm, $error);
       if (!$ok)
        print ("Cannot execute query: $error");


}






// look for a command update here then process it

sleep (1);
$l++;

if ($l == 30) {
  $serial->sendMessage("current\n");
  $l = 0;
  }

}




?> 

