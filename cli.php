<?php


///////////////////////////////////
//  p O W e R P e L L e T 
//
//  A PHP application for managing intelligent power strips
//  at a yearly video game convention.
//
//////////////////////////////////////////
//
// Pre-reqs
//  PHP5 php5-sqlite
//
//////////////////////////////////////////
//  Design
//
//  cli.php
//    A simple command line interface that managed data in sqlite
//    database tables. 
//
//  cycladesdaemon.php 
//    Backgruond process that scans the database for tasks then 
//    executes on them. Uses 1st command line option to determine which strip.
//
//////////////////////////////////////////
//
//  dbschema
//
//    This is the table with a row for each power strip, or chain of cyclades
//    power strips.
//
//    TABLE strips 
//          Id INTEGER PRIMARY KEY, - self explan
//             number INTEGER,      - unique number for strip/chain
//             type INTEGER,        - type (1/2) for demon to latch onto 
//             connection INTEGER,  - ethernet or direct serial
//             path CHAR[48],       - device or ip
//             tcpport INTEGER,     - tcp port for ip 
//             active INTEGER,      - 
//             desc CHAR[64],       - test description
//             outlets INTEGER,     - total number of outlets
//             chainedstrips INTEGER, - cyclades - how many are chained (For current tables)
//             login CHAR[32],      - login to use
//             pass CHAR[32],       - pass to use
//             baud INTEGER,        - baud rate for direct attached serial
//             parity CHAR[1],      - not needed, can go away
//             flow CHAR[1])";      - not needed, can go away
//
//
//    This table has a row for each port on each power strip or strip chain.
// 
//     TABLE devices (
//             Id INTEGER PRIMARY KEY, - self explanatory
//             stripnumber INTEGER,    - which strip # is the port on 
//             port INTEGER,           - which port is this for 
//             status INTEGER,         - currently on or off 
//             desc CHAR[128])";       - text description
//
//     This table is a job queue. Each task to be done goes in here, and the daemon for the strip
//     running in the background picks it up. Once it acts on it, it writes the status back.
//     This prevents sync issues.
//
//  
//     TABLE commands (
//             Id INTEGER PRIMARY KEY, - UID 
//             strip INTEGER,      - strip # 
//             port INTEGER,       - port # 
//             change INTEGER)";   - change being requested
//
//     The current amount of current being drawn as reported by
//     daemons. A trend is not kept in the database. Needs to be
//     written out if desired.
//
//     TABLE current (
//            Id INTEGER PRIMARY KEY, 
//            strip INTEGER,   - which strip 
//            substrip INTEGER, - which unit in a chain of strips 
//            average CHAR[6],  - the average amperage reported
//            peak CHAR[6])"    - the peak amperage reported
//
//////////////////////////////////////////
// 
//    FLAWS
//
//    There could probably stand to be a synchronization capability
//    where by the daemons read the  status of each port and
//    write it into the database.            
//
//
//////////////////////////////////////////





$dbhandle = sqlite_open('db/test.db', 0666, $error);
if (!$dbhandle) die ($error);

$fh = fopen('php://stdin', 'r');
$editingstripnumber = 1;


// Draws a simple header for the top of each menu
function drawHeader($text)
{
  print "\033[2J";
  print "\n";
  print ".--[ PowerPellet v.0.1a ]------------------------------------------+\n";
  print ":  $text    \n";
  print "`------------------------------------------------+\n";
  print "\n";

}


// Admin menu
function adminMenu()
{ 

while ($adminexit != 1) {

  drawHeader('Admin Menu');
  print "[P] ower Strip Add / Remove / Config \n";
  print "[D] evice edit. What is on which port \n";
  print "[I] nitialize databases \n"; 
  print "[Q] uit to main menu \n";
  $line = trim(fgets(STDIN));

  switch (strtolower($line)) {
    case 'q':
      $adminexit = 1;
      break;

    case 'i':
      initdb();
      break;

    case 'p':
      stripedit();
      break; 

    case 'd':
      deviceedit();
      break;

    case 's':
      selectstrip();
      break;

     }
    }
}





// I guess we could re-use this for the main menu strip selection

// Select which strip is being edited
function selectstrip()
{
  global $dbhandle,$editingstripnumber;

  $result = sqlite_query($dbhandle, "SELECT number, desc, type, path, outlets, chainedstrips FROM strips");

  print ". # . Name                            . T . Path         . Out . Start .\n" ;

  while ($row = sqlite_fetch_array($result, SQLITE_NUM)) {
     $outputstring = "                                             ";
     $outputstring = substr_replace ($outputstring, $row['0'], 2, 0 );
     $outputstring = substr_replace ($outputstring, $row['1'], 6, 0);
     $outputstring = substr_replace ($outputstring, $row['2'], 40, 0);
     $outputstring = substr_replace ($outputstring, $row['3'], 44, 0);
     $outputstring = substr_replace ($outputstring, $row['4'], 59, 0);
     print $outputstring . "\n";
  }

    print "Strip number:";

      $editingstripnumber = trim(fgets(STDIN));


}











function deviceedit()
{

  global $dbhandle,$editingstripnumber;
 
  $outletpage = 0;

  while ($exit != 1) {

     drawHeader('Device Edit Menu');
     print "\n";
     print "Selected strip :" . $editingstripnumber . "\n";

  // Get number of outlets so we can render a range if needed
    $stripresult = sqlite_fetch_array(sqlite_query($dbhandle, "SELECT outlets, desc FROM strips WHERE number = $editingstripnumber"), SQLITE_NUM);
    $outlets = $stripresult['0'];

    $result = sqlite_query($dbhandle, "SELECT port, desc FROM devices WHERE stripnumber = $editingstripnumber ORDER BY port LIMIT 15 offset $outletpage");

    print ". # . Desc                        .\n" ;

    $j = 0;
    while ($row = sqlite_fetch_array($result, SQLITE_NUM)) {
       $outputstring = "                                             ";
       $outputstring = substr_replace ($outputstring, $row['0'], 2, 0 );
       $outputstring = substr_replace ($outputstring, $row['1'], 6, 0);
       print $outputstring . "\n";
       $j++;
       }
 
    print "\n\n";
    print "[Q]uit, [1] to [$j] to edit device desc, Page [D]own/[U]p, [S]elect strip:";

    $line = trim(fgets(STDIN));

    if (is_numeric($line)) {

    if ((int)$line >= 1 || (int)$line <= $j) {
      print "\n\n32 characters max. \n";
      print "New description for port # $line :";
      $descline = trim(fgets(STDIN));

      $stm = "UPDATE devices SET desc='$descline' WHERE stripnumber='$editingstripnumber' AND port='$line'";
      $ok = sqlite_exec($dbhandle, $stm, $error);
          if (!$ok)
             print ("Cannot execute query: $error");
    }
    }

    switch (strtolower($line)) {

      case 'q':
        $exit = 1;
        break;

      case 's':
        selectStrip();
        break;

   case 'd':
      if ($outletpage < $outlets) {
      $outletpage = $outletpage + 15;
      }
      break;

    case 'u':
      if ($outletpage >= 15) {
      $outletpage = $outletpage - 15;
      }
      break;

    }

}
}






function stripedit()
{
 
  global $dbhandle;

while ($exit != 1) {

  drawHeader('Strip Edit Menu');

  $result = sqlite_query($dbhandle, "SELECT number, desc, type, path, outlets, chainedstrips FROM strips");

  print ". # . Name                            . T . Path         . Out . Chained .\n" ;
 
  while ($row = sqlite_fetch_array($result, SQLITE_NUM)) {
     $outputstring = "                                             ";
     $outputstring = substr_replace ($outputstring, $row['0'], 2, 0 );
     $outputstring = substr_replace ($outputstring, $row['1'], 6, 0);
     $outputstring = substr_replace ($outputstring, $row['2'], 40, 0);
     $outputstring = substr_replace ($outputstring, $row['3'], 44, 0);
     $outputstring = substr_replace ($outputstring, $row['4'], 59, 0);
     print $outputstring . "\n"; 
  }

   print "\n\n";

  print "[q]uit to admin, [a]dd new strip, [d]elete strip, strip # to edit:";

  $line = trim(fgets(STDIN));
  switch (strtolower($line)) {

    case 'q':
      $exit = 1;
      break;

    case 'd':
      print "Delete power strip. \n";
      print "Power strip number to delete:";
      $stripnumber = trim(fgets(STDIN));

      $stm = "DELETE FROM strips WHERE number='$stripnumber'";
      $ok = sqlite_exec($dbhandle, $stm, $error);
          if (!$ok)
             die ("Cannot execute query: $error");

      $stm = "DELETE FROM devices WHERE stripnumber='$stripnumber'";
      $ok = sqlite_exec($dbhandle, $stm, $error);
          if (!$ok)
             die ("Cannot execute query: $error");
      break;

    case 'a':
      print "\033[2J";
      print "Add new power strip. \n";
      print "\n";
      print "Unique number for power strip (ex. 1, 5, 12):";
      $stripnumber = trim(fgets(STDIN));

      print "\n\nType of strip\n [1] Cyclades Alterpath \n [2] Baytech RPC\n: ";
      $striptype = trim(fgets(STDIN));

      // There should be strip type logic here
     

      print "\n\nHow is it connected\n [1] Serial \n [2] Ethernet \n:";
      $stripconnection = trim(fgets(STDIN));

      print "\n\nPath for connection\n Ethernet connections, type the IP. \n";
      print " Serial connections, type the serial port (ex /dev/ttyUSB0)\n:";
      $strippath  = trim(fgets(STDIN));

      print "\n\nTCP Port # for ethernet connected strips (press enter for serial):\n";
      $stripport  = trim(fgets(STDIN));

      print "\n\nDescription of strip (128 char max)\n:";
      $stripdesc = trim(fgets(STDIN));

      print "\n\nNumber of outlets on strip, or chain of strips (Cyclades)\n";
      print "For baytech RPC-3, this is normally 8.\n";
      print "For Cyclades, this is 10 usually 10 per strip in the chain. \n";
      print ":";
      $stripoutlets = trim(fgets(STDIN));

      print "For cyclades that are chained, what is the total number of chained strips \n";
      print "ex. 3 strips are connected together, so it is 3.\n";
      $chainedstrips = trim(fgets(STDIN));

      print "\n\nLogin for strip:";
      $striplogin = trim(fgets(STDIN));

      print "\n\nPassword for strip:";
      $strippass = trim(fgets(STDIN));

      print "\n\nBaud rate for serial strip (press enter for ethernet):";
      $stripbaud = trim(fgets(STDIN));

      print "\n\nSerial parity rate (press enter for ethernet):";
      $stripparity = trim(fgets(STDIN));

      print "\n\nFlow control for serial (press enter for ethernet):";
      $stripflow = trim(fgets(STDIN));

      print "\n\n";
      print "Num: $stripnumber\nType: $striptype\nConnection: $stripconnection\nPath: $strippath\nPort: $stripport\nDescription: $stripdesc\n";
      print "Number of outlets: $stripoutlets\nLogin: $striplogin\nPass: $strippass\nBaud: $stripbaud\nParity: $stripparity\nFlow control: $stripflow\n\n";
      print "Okay to add this strip? (\"Y\" to confirm yes, \"N\" to abort)";

     $addline = trim(fgets(STDIN));
     switch (strtolower($addline)) {
     case 'y':
       print "Updating strip information!\n";
       $stm = "INSERT INTO strips (number, type, connection, path, tcpport, desc, outlets, chainedstrips, login, pass, baud, parity, flow) VALUES('$stripnumber', '$striptype', '$stripconnection', '$strippath', '$stripport', '$stripdesc', '$stripoutlets', '$chainedstrips', '$striplogin', '$strippass', '$stripbaud', '$stripparity', '$stripflow')";
       $ok = sqlite_exec ($dbhandle, $stm, $error);
       if (!$ok)
           die ("Cannot execute query: $error");

       print "Creating $stripoutlets device entries for strip!\n";

      // Loop through and create entries for each device on each strip.

       $j = 1;
       while ($j <= $stripoutlets) {

          $stm = "INSERT INTO devices (stripnumber, port, desc) VALUES('$stripnumber', '$j', 'No description')";

          $ok = sqlite_exec($dbhandle, $stm, $error);
          if (!$ok)
             die ("Cannot execute query: $error");
          $j++;
        } 

      // Loop through creating default line entries for each power current level for multiple chained strips.

      print "Creating current entries for $chainedstrips units!\n";
      $j = 1;
       while ($j <= $chainedstrips) {
         
       $stm = "INSERT INTO current (strip, substrip, average, peak) VALUES ('$stripnumber', '$j', 'init', 'init')";       
       $ok = sqlite_exec($dbhandle, $stm, $error);
       if (!$ok)
          die ("Cannot execute query: $error");
       $j++;
      } 


       print "Complete. Press enter to continue.";
       $line = trim(fgets(STDIN));
       break;
      } 
      
  }

}
}

















function setport($mode)
{

   global $dbhandle,$modstripnumber,$striparray;


   if ($mode == '0') {
     print ("\nPort to turn off:");
     $input = trim(fgets(STDIN));

     if (is_numeric($input)) {

      $stm = "INSERT INTO commands (strip, port, change) VALUES ('$striparray[$modstripnumber]', '$input', '0')";
      $ok = sqlite_exec($dbhandle, $stm, $error);
          if (!$ok)
             print ("Cannot execute query: $error");
          }
      }

   if ($mode == '1') {
     print ("\nPort to turn on:");
     $input = trim(fgets(STDIN));

     if (is_numeric($input)) {

      $stm = "INSERT INTO commands (strip, port, change) VALUES ('$striparray[$modstripnumber]', '$input', '1')";
      $ok = sqlite_exec($dbhandle, $stm, $error);
          if (!$ok)
             print ("Cannot execute query: $error");
    }
   }

}

















function initdb()
{

 global $dbhandle;

 print "\033[2J";
 print "This function will erase all databases then create them fresh.\n";
 print "THIS IS DESTRUCTIVE. DO YOU WISH TO PROCEED?\n";
 print "ALL CURRENT SETUP INFORMATION WILL BE LOST!\n";
 print "Type \"resetit\" to erase, or hit enter to abort:";

  $line = trim(fgets(STDIN));
  switch (strtolower($line)) {
    case 'resetit':
       print "Reseting DBs!!1!!!!\n";
       sqlite_exec($dbhandle, "drop table strips", $error);
       sqlite_exec($dbhandle, "drop table devices", $error);
       sqlite_exec($dbhandle, "drop table current", $error);
       sqlite_exec($dbhandle, "drop table commands", $error);


       $stm = "CREATE TABLE strips (Id INTEGER PRIMARY KEY,
               number INTEGER, type INTEGER, connection INTEGER,
               path CHAR[48], tcpport INTEGER, active INTEGER,
               desc CHAR[64], outlets INTEGER, chainedstrips INTEGER, login CHAR[32],
               pass CHAR[32], baud INTEGER, parity CHAR[1],
               flow CHAR[1])";
       $ok = sqlite_exec($dbhandle, $stm, $error);

       if (!$ok)
           die ("Cannot execute query: $error");

       echo "Database strips created successfully\n";





       $stm = "CREATE TABLE devices (Id INTEGER PRIMARY KEY,
               stripnumber INTEGER, port INTEGER, status INTEGER, desc CHAR[128])";
       $ok = sqlite_exec($dbhandle, $stm, $error);

       if (!$ok)
           die ("Cannot execute query: $error");

       echo "Database devices created successfully\n";




       $stm = "CREATE TABLE commands (Id INTEGER PRIMARY KEY, strip INTEGER, port INTEGER, change INTEGER)";
       $ok = sqlite_exec($dbhandle, $stm, $error);

       if (!$ok)
           die ("Cannot execute query: $error");

       echo "Database commands created successfully\n";


       $stm = "CREATE TABLE current (Id INTEGER PRIMARY KEY, strip INTEGER, substrip INTEGER, average CHAR[6], peak CHAR[6])";
       $ok = sqlite_exec($dbhandle, $stm, $error);

       if (!$ok)
           die ("Cannot execute query: $error");

       echo "Database current created successfully\n";





       print "Complete. Press enter to continue.";
       $line = trim(fgets(STDIN));
       break;
 }
}








$modstripnumber = 0;
$outletpage = 0;


while ($exit != 1) {

    // Copy into an array the list of strips that have database entries so we can next/previous
    $stripcount = 0;

    $result = sqlite_query($dbhandle, "SELECT number FROM strips ORDER BY number");

     while ($row2 = sqlite_fetch_array($result, SQLITE_NUM)) {
       print ("DEBUG=".$row2);
       $striparray[$stripcount] = $row2['0'];
       $stripcount++;
      }


    // Get number of outlets and strip description so we can page through ports if needed
    $stripresult = sqlite_fetch_array(sqlite_query($dbhandle, "SELECT outlets, desc FROM strips WHERE number = $striparray[$modstripnumber]"), SQLITE_NUM);
    $outlets = $stripresult['0'];




    $result = sqlite_query($dbhandle, "SELECT port, desc, status FROM devices WHERE stripnumber = $striparray[$modstripnumber] ORDER BY port LIMIT 15 offset $outletpage");

   drawHeader('Main Menu');
  
 
    print "Strip: " . $striparray[$modstripnumber] . "      Desc: " . $stripresult['1'] . "     Outlets: " . $outlets . "\n\n";
    print ". # . Desc                        . Status \n" ;

    // Print the list of ports, and their status
    $j = 0;
    while ($row = sqlite_fetch_array($result, SQLITE_NUM)) {
       $outputstring = "                                             ";
       $outputstring = substr_replace ($outputstring, $row['0'], 2, 0 );
       $outputstring = substr_replace ($outputstring, $row['1'], 6, 0);
       if ($row['2'] == '1') {
       $outputstring = substr_replace ($outputstring, "On", 36, 0);
       } else {
       $outputstring = substr_replace ($outputstring, "Off", 36, 0);
       }
       print $outputstring . "\n";
       $j++;
      }



  print "\nTurn [O]n port, [Z]Turn off port, [C]urrent, [!]Admin, [N]ext/[P]rev strip, Page [D]own/[U]p, [Q]uit :";

  
  $line = trim(fgets(STDIN));
  switch (strtolower($line)) {

    case 'q':
      $exit = 1;
      sqlite_close($dbhandle);
      exit();

    case '!':
      adminMenu();
      break;

    case 'o':
      setport(1);
      break;

    case 'z':
      setport(0);
      break;

    case 'n':
      if ($modstripnumber != ($stripcount - 1)) {
         $modstripnumber++;
      }
      break; 

    case 'p':
      if ($modstripnumber > 0) {
         $modstripnumber = $modstripnumber - 1;
       }
       break;

    case 'd':
      if ($outletpage < $outlets) {
      $outletpage = $outletpage + 15;
      }
      break;

    case 'u':
      if ($outletpage >= 15) {
      $outletpage = $outletpage - 15;
      }
      break;
   }
}

 


?>

